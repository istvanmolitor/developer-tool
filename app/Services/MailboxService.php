<?php

namespace App\Services;

use App\Enums\MailEncryption;
use App\Enums\OutgoingEmailStatus;
use App\Mail\MailboxMessage;
use App\Models\Attachment;
use App\Models\IncomingEmail;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use App\Models\PromptRun;
use App\Models\User;
use App\Services\Mail\FetchedAttachment;
use App\Services\Mail\FetchedEmail;
use App\Services\Mail\ImapMailFetcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Downloads incoming mail into `incoming_emails`, hands messages to the
 * mailbox's agent to read, records the agent's replies, and sends mail through
 * a mailbox's own SMTP account.
 */
class MailboxService
{
    /**
     * Longest message body put into the agent's prompt; a CLI argument cannot be arbitrarily long.
     */
    private const PROMPT_BODY_LIMIT = 50_000;

    public function __construct(
        private ImapMailFetcher $fetcher,
        private ClaudePromptRunner $runner,
        private AttachmentService $attachments,
    ) {}

    /**
     * Download the messages not yet stored for this mailbox. When the mailbox
     * has automatic reading on, its agent reads every new message right away.
     *
     * @return int The number of newly stored messages.
     */
    public function fetchIncoming(Mailbox $mailbox, int $limit = 100): int
    {
        // Deleted messages no longer show up in max('uid'), so the stored high-water mark wins.
        $lastUid = max((int) $mailbox->last_uid, (int) $mailbox->incomingEmails()->max('uid'));
        $created = [];

        foreach ($this->fetcher->fetch($mailbox, $lastUid, $limit) as $email) {
            $incoming = $this->storeIncoming($mailbox, $email);

            if ($incoming !== null) {
                $created[] = $incoming;
            }

            $lastUid = max($lastUid, $email->uid);
        }

        $mailbox->forceFill(['last_fetched_at' => now(), 'last_uid' => $lastUid ?: null])->save();

        // Without an agent or project (e.g. one was deleted since) the messages stay unread for later.
        if ($mailbox->auto_read && $mailbox->canAgentRead()) {
            foreach ($created as $incoming) {
                $this->sendToAgent($incoming);
            }
        }

        return count($created);
    }

    /**
     * Have the mailbox's agent read the message: queue a prompt run with the
     * message on the mailbox's project and mark the message as read.
     *
     * @throws RuntimeException When the message was already read or the mailbox has no agent or project.
     */
    public function sendToAgent(IncomingEmail $incomingEmail, ?User $user = null): PromptRun
    {
        return DB::transaction(function () use ($incomingEmail, $user): PromptRun {
            // Lock the row so a double click cannot hand the same message over twice.
            $locked = IncomingEmail::query()->lockForUpdate()->findOrFail($incomingEmail->getKey());
            $mailbox = $locked->mailbox()->with(['agent', 'project'])->firstOrFail();

            if ($locked->isRead()) {
                throw new RuntimeException('Ezt a levelet az agent már elolvasta.');
            }

            if ($mailbox->agent === null || $mailbox->project === null) {
                throw new RuntimeException('A postafiókhoz előbb állíts be agentet és projektet.');
            }

            $run = $this->runner->queueAgent($mailbox->project, $mailbox->agent, $this->agentPrompt($mailbox, $locked), $user);

            $locked->forceFill(['read_at' => now(), 'prompt_run_id' => $run->id])->save();
            $incomingEmail->setRawAttributes($locked->getAttributes(), true);

            return $run;
        });
    }

    /**
     * The prompt that hands a message to the agent: its headers, attachment files and text.
     */
    public function agentPrompt(Mailbox $mailbox, IncomingEmail $incomingEmail): string
    {
        $from = $incomingEmail->from_name !== null && $incomingEmail->from_name !== ''
            ? "{$incomingEmail->from_name} <{$incomingEmail->from_address}>"
            : ($incomingEmail->from_address ?? 'ismeretlen');

        $lines = [
            "Új e-mail érkezett a(z) „{$mailbox->name}” ({$mailbox->email}) postafiókba, amelyet te kezelsz.",
            "Olvasd el, és a tartalma alapján végezd el a teendőket a(z) „{$mailbox->project?->name}” projekten.",
            '',
            "Feladó: {$from}",
            'Címzettek: '.(implode(', ', $incomingEmail->to ?? []) ?: '—'),
        ];

        if (! empty($incomingEmail->cc)) {
            $lines[] = 'Másolat: '.implode(', ', $incomingEmail->cc);
        }

        $lines[] = 'Tárgy: '.($incomingEmail->subject ?: '(nincs tárgy)');
        $lines[] = 'Érkezett: '.($incomingEmail->received_at?->toDateTimeString() ?? '—');
        $lines[] = '';
        $lines[] = "Ha a feladónak válaszolni kell, a `reply-email` eszközzel tedd meg (incoming_email_id: {$incomingEmail->id}). "
            ."A válasz a(z) {$mailbox->email} címről megy ki. Levelenként csak egyszer válaszolhatsz, ezért előbb végezd el a teendőket.";

        $attachments = $incomingEmail->attachments()->orderBy('attachments.id')->get();

        if ($attachments->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Csatolmányok (a fájlok ezeken az útvonalakon olvashatók):';

            foreach ($attachments as $attachment) {
                $lines[] = "- {$attachment->filename} ({$attachment->mime_type}): ".$attachment->absolutePath();
            }
        }

        $body = trim((string) ($incomingEmail->body_text ?: strip_tags((string) $incomingEmail->body_html)));

        if (mb_strlen($body) > self::PROMPT_BODY_LIMIT) {
            $body = mb_substr($body, 0, self::PROMPT_BODY_LIMIT)."\n\n[… a levél további része levágva]";
        }

        $lines[] = '';
        $lines[] = '--- A levél szövege ---';
        $lines[] = $body !== '' ? $body : '(nincs szöveges tartalom)';
        $lines[] = '--- A levél vége ---';

        return implode("\n", $lines);
    }

    /**
     * Store one fetched message with its attachment files, unless it is already stored.
     *
     * @return IncomingEmail|null The newly stored message, or null when it was already stored.
     */
    private function storeIncoming(Mailbox $mailbox, FetchedEmail $email): ?IncomingEmail
    {
        // Paths of files written this round, so a rollback can drop the orphans
        // (Storage writes are not part of the database transaction).
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($mailbox, $email, &$storedPaths): ?IncomingEmail {
                $incoming = $mailbox->incomingEmails()->firstOrCreate(
                    ['uid' => $email->uid],
                    [
                        'message_id' => $email->messageId,
                        'from_address' => $email->fromAddress,
                        'from_name' => $email->fromName,
                        'to' => $email->to,
                        'cc' => $email->cc,
                        'subject' => $email->subject,
                        'body_text' => $email->bodyText,
                        'body_html' => $email->bodyHtml,
                        'received_at' => $email->receivedAt,
                    ],
                );

                if (! $incoming->wasRecentlyCreated) {
                    return null;
                }

                // The attachment's project comes from the mailbox; set the relation
                // so storeForEmail need not reload it.
                $incoming->setRelation('mailbox', $mailbox);

                foreach ($email->attachments as $attachment) {
                    $storedPaths[] = $this->storeAttachment($incoming, $attachment)->path;
                }

                return $incoming;
            });
        } catch (Throwable $exception) {
            // The message row was rolled back, so drop any files already written for it.
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }
    }

    private function storeAttachment(IncomingEmail $incoming, FetchedAttachment $attachment): Attachment
    {
        return $this->attachments->storeForEmail(
            $incoming,
            $attachment->filename,
            $attachment->content,
            $attachment->mimeType,
        );
    }

    /**
     * Record the agent's reply to a message, from the mailbox the message arrived in.
     * With automatic replies on it is sent right away; otherwise it waits as a draft.
     *
     * @throws RuntimeException When the message has no sender or was already answered.
     */
    public function reply(IncomingEmail $incomingEmail, string $body, ?PromptRun $promptRun = null): OutgoingEmail
    {
        $outgoing = DB::transaction(function () use ($incomingEmail, $body, $promptRun): OutgoingEmail {
            // Lock the message so two parallel calls cannot both answer it.
            $locked = IncomingEmail::query()->lockForUpdate()->findOrFail($incomingEmail->getKey());

            if ($locked->from_address === null || $locked->from_address === '') {
                throw new RuntimeException('A levélnek nincs feladója, ezért nem lehet rá válaszolni.');
            }

            if ($locked->replies()->exists()) {
                throw new RuntimeException('Erre a levélre már született válasz.');
            }

            return $locked->replies()->create([
                'mailbox_id' => $locked->mailbox_id,
                'prompt_run_id' => $promptRun?->id,
                'to' => [$locked->from_address],
                'subject' => $this->replySubject($locked->subject),
                'body_text' => $body,
                'in_reply_to' => $locked->message_id,
                'status' => OutgoingEmailStatus::Draft,
            ]);
        });

        if ($outgoing->mailbox->auto_reply) {
            $this->sendOutgoing($outgoing);
        }

        return $outgoing;
    }

    /**
     * Send a draft (or retry a failed) message through its mailbox's SMTP account.
     * A failed send is recorded on the message instead of being thrown.
     *
     * @throws RuntimeException When the message was already sent.
     */
    public function sendOutgoing(OutgoingEmail $outgoingEmail): OutgoingEmail
    {
        DB::transaction(function () use ($outgoingEmail): void {
            // Lock the row so a double click cannot send the same message twice.
            $locked = OutgoingEmail::query()->lockForUpdate()->findOrFail($outgoingEmail->getKey());

            if (! $locked->status->isSendable()) {
                throw new RuntimeException('Ez a levél már el lett küldve.');
            }

            $mailbox = $locked->mailbox()->firstOrFail();

            try {
                Mail::mailer($this->registerMailer($mailbox))
                    ->to($locked->to)
                    ->send(new MailboxMessage($mailbox, $locked->subject, $locked->body_text, inReplyTo: $locked->in_reply_to));

                $locked->forceFill(['status' => OutgoingEmailStatus::Sent, 'sent_at' => now(), 'error' => null])->save();
            } catch (Throwable $exception) {
                report($exception);

                $locked->forceFill(['status' => OutgoingEmailStatus::Failed, 'error' => $exception->getMessage()])->save();
            }

            $outgoingEmail->setRawAttributes($locked->getAttributes(), true);
        });

        return $outgoingEmail;
    }

    /**
     * The subject of a reply: the original one with a single "Re:" prefix.
     */
    private function replySubject(?string $subject): string
    {
        $subject = trim((string) $subject);

        if ($subject === '') {
            return 'Re: (nincs tárgy)';
        }

        $subject = preg_match('/^re:/i', $subject) === 1 ? $subject : "Re: {$subject}";

        // The subject column holds 255 characters.
        return mb_substr($subject, 0, 255);
    }

    /**
     * Send a message from the mailbox's address via its SMTP account.
     *
     * @param  string|list<string>  $to
     */
    public function send(Mailbox $mailbox, string|array $to, string $subject, string $body, bool $isHtml = false): void
    {
        Mail::mailer($this->registerMailer($mailbox))
            ->to($to)
            ->send(new MailboxMessage($mailbox, $subject, $body, $isHtml));
    }

    /**
     * Register (or refresh) a runtime SMTP mailer for the mailbox and return its name.
     */
    private function registerMailer(Mailbox $mailbox): string
    {
        $name = 'mailbox_'.$mailbox->id;

        config(["mail.mailers.{$name}" => [
            'transport' => 'smtp',
            'scheme' => $mailbox->smtp_encryption === MailEncryption::Ssl ? 'smtps' : 'smtp',
            'host' => $mailbox->smtp_host,
            'port' => $mailbox->smtp_port,
            'username' => $mailbox->username,
            'password' => $mailbox->password,
            'require_tls' => $mailbox->smtp_encryption === MailEncryption::Tls,
            'auto_tls' => $mailbox->smtp_encryption !== MailEncryption::None,
            'timeout' => 30,
        ]]);

        // Drop a mailer cached with stale settings (e.g. after the mailbox was edited).
        Mail::purge($name);

        return $name;
    }
}

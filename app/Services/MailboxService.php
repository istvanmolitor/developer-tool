<?php

namespace App\Services;

use App\Enums\MailEncryption;
use App\Mail\MailboxMessage;
use App\Models\IncomingEmail;
use App\Models\Mailbox;
use App\Services\Mail\FetchedAttachment;
use App\Services\Mail\FetchedEmail;
use App\Services\Mail\ImapMailFetcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Downloads incoming mail into `incoming_emails` and sends mail through a
 * mailbox's own SMTP account.
 */
class MailboxService
{
    public function __construct(private ImapMailFetcher $fetcher) {}

    /**
     * Download the messages not yet stored for this mailbox.
     *
     * @return int The number of newly stored messages.
     */
    public function fetchIncoming(Mailbox $mailbox, int $limit = 100): int
    {
        // Deleted messages no longer show up in max('uid'), so the stored high-water mark wins.
        $lastUid = max((int) $mailbox->last_uid, (int) $mailbox->incomingEmails()->max('uid'));
        $created = 0;

        foreach ($this->fetcher->fetch($mailbox, $lastUid, $limit) as $email) {
            if ($this->storeIncoming($mailbox, $email)) {
                $created++;
            }

            $lastUid = max($lastUid, $email->uid);
        }

        $mailbox->forceFill(['last_fetched_at' => now(), 'last_uid' => $lastUid ?: null])->save();

        return $created;
    }

    /**
     * Store one fetched message with its attachment files, unless it is already stored.
     *
     * @return bool Whether the message was newly stored.
     */
    private function storeIncoming(Mailbox $mailbox, FetchedEmail $email): bool
    {
        $directory = null;

        try {
            return DB::transaction(function () use ($mailbox, $email, &$directory): bool {
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
                    return false;
                }

                $directory = $incoming->attachmentsDirectory();

                foreach ($email->attachments as $attachment) {
                    $this->storeAttachment($incoming, $attachment);
                }

                return true;
            });
        } catch (Throwable $exception) {
            // The message row was rolled back, so drop any files already written for it.
            if ($directory !== null) {
                Storage::disk('local')->deleteDirectory($directory);
            }

            throw $exception;
        }
    }

    private function storeAttachment(IncomingEmail $incoming, FetchedAttachment $attachment): void
    {
        // Files are stored under a random name; the sender's file name is only kept in the database.
        $path = $incoming->attachmentsDirectory().'/'.Str::uuid();

        if (! Storage::disk('local')->put($path, $attachment->content)) {
            throw new RuntimeException("Nem sikerült menteni a(z) „{$attachment->filename}” csatolmányt.");
        }

        $incoming->attachments()->create([
            'filename' => $attachment->filename,
            'mime_type' => $attachment->mimeType,
            'size' => strlen($attachment->content),
            'path' => $path,
        ]);
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

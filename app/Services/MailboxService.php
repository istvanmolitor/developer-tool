<?php

namespace App\Services;

use App\Enums\MailEncryption;
use App\Mail\MailboxMessage;
use App\Models\Mailbox;
use App\Services\Mail\ImapMailFetcher;
use Illuminate\Support\Facades\Mail;

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
        $lastUid = (int) $mailbox->incomingEmails()->max('uid');
        $created = 0;

        foreach ($this->fetcher->fetch($mailbox, $lastUid, $limit) as $email) {
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

            if ($incoming->wasRecentlyCreated) {
                $created++;
            }
        }

        $mailbox->forceFill(['last_fetched_at' => now()])->save();

        return $created;
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

<?php

namespace App\Services\Mail;

use App\Enums\MailEncryption;
use App\Models\Mailbox;
use Carbon\CarbonImmutable;
use IMAP\Connection;
use RuntimeException;
use stdClass;
use Throwable;
use ValueError;

/**
 * Downloads messages from a mailbox over IMAP using PHP's native imap extension.
 *
 * The folder is opened read-only and bodies are fetched with FT_PEEK, so
 * downloading never marks messages as seen on the server.
 */
class ImapMailFetcher
{
    /**
     * Fetch the messages whose UID is greater than `$afterUid`, oldest first.
     *
     * @return list<FetchedEmail>
     */
    public function fetch(Mailbox $mailbox, int $afterUid = 0, int $limit = 100): array
    {
        $connection = $this->connect($mailbox);

        try {
            $uids = imap_search($connection, 'ALL', SE_UID) ?: [];
            $uids = array_values(array_filter($uids, fn (int $uid): bool => $uid > $afterUid));
            sort($uids);

            return array_map(
                fn (int $uid): FetchedEmail => $this->fetchMessage($connection, $uid),
                array_slice($uids, 0, $limit),
            );
        } finally {
            imap_close($connection);
            // Drain the extension's error/alert stacks so they don't surface as notices.
            imap_errors();
            imap_alerts();
        }
    }

    /**
     * The c-client mailbox reference, e.g. `{mail.example.com:993/imap/ssl}INBOX`.
     */
    public function mailboxReference(Mailbox $mailbox): string
    {
        $flags = match ($mailbox->imap_encryption) {
            MailEncryption::Ssl => '/imap/ssl',
            MailEncryption::Tls => '/imap/tls',
            MailEncryption::None => '/imap/notls',
        };

        return sprintf('{%s:%d%s}%s', $mailbox->imap_host, $mailbox->imap_port, $flags, $mailbox->imap_folder);
    }

    private function connect(Mailbox $mailbox): Connection
    {
        $connection = @imap_open(
            $this->mailboxReference($mailbox),
            $mailbox->username,
            $mailbox->password,
            OP_READONLY,
            1,
        );

        if ($connection === false) {
            $error = imap_last_error() ?: 'ismeretlen hiba';
            imap_errors();
            imap_alerts();

            throw new RuntimeException("Nem sikerült csatlakozni az IMAP szerverhez ({$mailbox->name}): {$error}");
        }

        return $connection;
    }

    private function fetchMessage(Connection $connection, int $uid): FetchedEmail
    {
        $headers = imap_rfc822_parse_headers((string) imap_fetchheader($connection, $uid, FT_UID));
        $from = $headers->from[0] ?? null;

        $bodies = ['text' => null, 'html' => null];
        $structure = imap_fetchstructure($connection, $uid, FT_UID);

        if ($structure !== false) {
            $this->collectBodies($connection, $uid, $structure, '', $bodies);
        }

        return new FetchedEmail(
            uid: $uid,
            messageId: isset($headers->message_id) ? trim($headers->message_id) : null,
            fromAddress: $from !== null ? $this->formatAddress($from) : null,
            fromName: isset($from->personal) ? $this->decodeHeader($from->personal) : null,
            to: $this->addressList($headers->to ?? []),
            cc: $this->addressList($headers->cc ?? []),
            subject: isset($headers->subject) ? $this->decodeHeader($headers->subject) : null,
            bodyText: $bodies['text'],
            bodyHtml: $bodies['html'],
            receivedAt: $this->parseDate($headers->date ?? null),
        );
    }

    /**
     * Walk the MIME tree and keep the first inline text/plain and text/html parts.
     *
     * @param  array{text: ?string, html: ?string}  $bodies
     */
    private function collectBodies(Connection $connection, int $uid, stdClass $part, string $section, array &$bodies): void
    {
        if (! empty($part->parts)) {
            // Attached messages (message/rfc822) are skipped — their text isn't this mail's body.
            if ($part->type === TYPEMESSAGE) {
                return;
            }

            foreach ($part->parts as $index => $child) {
                $childSection = $section === '' ? (string) ($index + 1) : $section.'.'.($index + 1);
                $this->collectBodies($connection, $uid, $child, $childSection, $bodies);
            }

            return;
        }

        if ($part->type !== TYPETEXT || $this->isAttachment($part)) {
            return;
        }

        $key = match (strtoupper($part->subtype ?? '')) {
            'PLAIN' => 'text',
            'HTML' => 'html',
            default => null,
        };

        if ($key === null || $bodies[$key] !== null) {
            return;
        }

        $raw = $section === ''
            ? imap_body($connection, $uid, FT_UID | FT_PEEK)
            : imap_fetchbody($connection, $uid, $section, FT_UID | FT_PEEK);

        $bodies[$key] = $this->toUtf8(
            $this->decodeTransferEncoding((string) $raw, (int) ($part->encoding ?? ENC7BIT)),
            $this->partParameter($part, 'charset'),
        );
    }

    private function isAttachment(stdClass $part): bool
    {
        if (isset($part->disposition) && strtolower($part->disposition) === 'attachment') {
            return true;
        }

        return $this->partParameter($part, 'filename') !== null || $this->partParameter($part, 'name') !== null;
    }

    private function partParameter(stdClass $part, string $name): ?string
    {
        foreach (['parameters', 'dparameters'] as $property) {
            if (empty($part->{'if'.$property}) || ! isset($part->{$property})) {
                continue;
            }

            foreach ($part->{$property} as $parameter) {
                if (strtolower($parameter->attribute) === $name) {
                    return $parameter->value;
                }
            }
        }

        return null;
    }

    private function decodeTransferEncoding(string $raw, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => (string) base64_decode($raw),
            ENCQUOTEDPRINTABLE => quoted_printable_decode($raw),
            default => $raw,
        };
    }

    private function toUtf8(string $value, ?string $charset): string
    {
        $charset = $charset !== null ? strtoupper($charset) : null;

        if ($charset !== null && ! in_array($charset, ['UTF-8', 'US-ASCII', 'DEFAULT'], true)) {
            try {
                $converted = mb_convert_encoding($value, 'UTF-8', $charset);

                if ($converted !== false) {
                    $value = $converted;
                }
            } catch (ValueError) {
                // Unknown charset — fall through and scrub whatever we have.
            }
        }

        return mb_scrub($value, 'UTF-8');
    }

    private function decodeHeader(string $value): string
    {
        $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return mb_scrub($decoded !== false ? $decoded : $value, 'UTF-8');
    }

    private function formatAddress(stdClass $address): ?string
    {
        if (! isset($address->mailbox, $address->host)) {
            return null;
        }

        return strtolower($address->mailbox.'@'.$address->host);
    }

    /**
     * @param  array<int, stdClass>  $addresses
     * @return list<string>
     */
    private function addressList(array $addresses): array
    {
        return array_values(array_filter(array_map($this->formatAddress(...), $addresses)));
    }

    private function parseDate(?string $date): ?CarbonImmutable
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date);
        } catch (Throwable) {
            return null;
        }
    }
}

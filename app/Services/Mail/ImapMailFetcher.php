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

        $content = ['text' => null, 'html' => null, 'attachments' => []];
        $structure = imap_fetchstructure($connection, $uid, FT_UID);

        if ($structure !== false) {
            $this->collectParts($connection, $uid, $structure, '', $content);
        }

        return new FetchedEmail(
            uid: $uid,
            messageId: isset($headers->message_id) ? trim($headers->message_id) : null,
            fromAddress: $from !== null ? $this->formatAddress($from) : null,
            fromName: isset($from->personal) ? $this->decodeHeader($from->personal) : null,
            to: $this->addressList($headers->to ?? []),
            cc: $this->addressList($headers->cc ?? []),
            subject: isset($headers->subject) ? $this->decodeHeader($headers->subject) : null,
            bodyText: $content['text'],
            bodyHtml: $content['html'],
            receivedAt: $this->parseDate($headers->date ?? null),
            attachments: $content['attachments'],
        );
    }

    /**
     * Walk the MIME tree: keep the first inline text/plain and text/html parts as
     * the bodies and every other leaf part (or attached message) as an attachment.
     *
     * @param  array{text: ?string, html: ?string, attachments: list<FetchedAttachment>}  $content
     */
    private function collectParts(Connection $connection, int $uid, stdClass $part, string $section, array &$content): void
    {
        // Attached messages (message/rfc822) are kept whole as .eml files, not merged into this mail's body.
        if ($part->type === TYPEMESSAGE && $section !== '') {
            $content['attachments'][] = new FetchedAttachment(
                filename: $this->attachmentFilename($part, $section, 'eml'),
                mimeType: 'message/rfc822',
                content: $this->fetchPart($connection, $uid, $part, $section),
            );

            return;
        }

        if (! empty($part->parts)) {
            foreach ($part->parts as $index => $child) {
                $childSection = $section === '' ? (string) ($index + 1) : $section.'.'.($index + 1);
                $this->collectParts($connection, $uid, $child, $childSection, $content);
            }

            return;
        }

        $bodyKey = $part->type === TYPETEXT && ! $this->isAttachment($part)
            ? match (strtoupper($part->subtype ?? '')) {
                'PLAIN' => 'text',
                'HTML' => 'html',
                default => null,
            }
        : null;

        if ($bodyKey !== null) {
            if ($content[$bodyKey] === null) {
                $content[$bodyKey] = $this->toUtf8(
                    $this->fetchPart($connection, $uid, $part, $section),
                    $this->partParameter($part, 'charset'),
                );
            }

            return;
        }

        // Unnamed inline text (e.g. a text/calendar alternative) is not a file the user attached.
        if ($part->type === TYPETEXT && ! $this->isAttachment($part)) {
            return;
        }

        $content['attachments'][] = new FetchedAttachment(
            filename: $this->attachmentFilename($part, $section),
            mimeType: $this->mimeType($part),
            content: $this->fetchPart($connection, $uid, $part, $section),
        );
    }

    /**
     * Download one MIME part and undo its transfer encoding.
     */
    private function fetchPart(Connection $connection, int $uid, stdClass $part, string $section): string
    {
        $raw = $section === ''
            ? imap_body($connection, $uid, FT_UID | FT_PEEK)
            : imap_fetchbody($connection, $uid, $section, FT_UID | FT_PEEK);

        return $this->decodeTransferEncoding((string) $raw, (int) ($part->encoding ?? ENC7BIT));
    }

    private function mimeType(stdClass $part): string
    {
        $type = match ($part->type) {
            TYPETEXT => 'text',
            TYPEMULTIPART => 'multipart',
            TYPEMESSAGE => 'message',
            TYPEAUDIO => 'audio',
            TYPEIMAGE => 'image',
            TYPEVIDEO => 'video',
            TYPEMODEL => 'model',
            default => 'application',
        };

        $subtype = strtolower($part->subtype ?? '');

        return $subtype !== '' ? "{$type}/{$subtype}" : 'application/octet-stream';
    }

    /**
     * The decoded file name of the part, or a generated one when the sender gave none.
     */
    private function attachmentFilename(stdClass $part, string $section, ?string $fallbackExtension = null): string
    {
        $filename = $this->partParameter($part, 'filename') ?? $this->partParameter($part, 'name');

        // Only RFC 2047 encoded-words need decoding; RFC 2231 values are already UTF-8 here.
        if ($filename !== null && str_contains($filename, '=?')) {
            $filename = $this->decodeHeader($filename);
        }

        $filename = trim($filename ?? '');

        // Never let a sender-supplied name carry path segments or control characters.
        $filename = (string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $filename);

        if ($filename === '' || $filename === '.' || $filename === '..') {
            $extension = $fallbackExtension ?? strtolower($part->subtype ?? 'bin');
            $filename = 'csatolmany-'.str_replace('.', '-', $section ?: '1').'.'.$extension;
        }

        return mb_substr($filename, 0, 255);
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

            $extended = [];

            foreach ($part->{$property} as $parameter) {
                $attribute = strtolower($parameter->attribute);

                if ($attribute === $name) {
                    return $parameter->value;
                }

                // RFC 2231 form: name*, or continuations name*0, name*1*, …
                if (preg_match('/^'.preg_quote($name, '/').'\*(\d*)(\*?)$/', $attribute, $matches) === 1) {
                    $extended[(int) $matches[1]] = [$parameter->value, $matches[1] === '' || $matches[2] === '*'];
                }
            }

            if ($extended !== []) {
                return $this->decodeExtendedParameter($extended);
            }
        }

        return null;
    }

    /**
     * Join and decode RFC 2231 parameter pieces (`utf-8''%C3%A1rv%C3%ADz.pdf`).
     *
     * @param  array<int, array{0: string, 1: bool}>  $pieces  value and whether it is percent-encoded, keyed by index
     */
    private function decodeExtendedParameter(array $pieces): string
    {
        ksort($pieces);
        $charset = null;
        $value = '';

        foreach ($pieces as $index => [$piece, $isEncoded]) {
            if ($index === array_key_first($pieces) && $isEncoded && substr_count($piece, "'") >= 2) {
                [$charset, , $piece] = explode("'", $piece, 3);
            }

            $value .= $isEncoded ? rawurldecode($piece) : $piece;
        }

        return $this->toUtf8($value, $charset !== '' ? $charset : null);
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

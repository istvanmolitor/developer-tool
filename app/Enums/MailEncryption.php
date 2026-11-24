<?php

namespace App\Enums;

/**
 * Transport security used for an IMAP / SMTP connection.
 */
enum MailEncryption: string
{
    /** Implicit TLS from the first byte (IMAP 993, SMTP 465). */
    case Ssl = 'ssl';

    /** Plain connection upgraded with STARTTLS (IMAP 143, SMTP 587). */
    case Tls = 'tls';

    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Ssl => 'SSL / TLS',
            self::Tls => 'STARTTLS',
            self::None => 'Nincs',
        };
    }
}

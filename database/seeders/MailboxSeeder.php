<?php

namespace Database\Seeders;

use App\Enums\MailEncryption;
use App\Models\Mailbox;
use Illuminate\Database\Seeder;

class MailboxSeeder extends Seeder
{
    private const SERVER_HOST = 'mail.dunahousemail.hu';

    private const IMAP_PORT = 993;

    private const SMTP_PORT = 465;

    /**
     * Upsert the mailboxes of `developer-tool.mailbox_passwords` by e-mail address,
     * so re-running the seeder refreshes their credentials instead of duplicating
     * rows. The passwords come from the environment; one without a password is skipped.
     */
    public function run(): void
    {
        /** @var array<string, string|null> $passwords */
        $passwords = config('developer-tool.mailbox_passwords', []);

        foreach ($passwords as $email => $password) {
            if ($password === null || $password === '') {
                $this->command?->warn("Nincs jelszó megadva a(z) {$email} postafiókhoz, kihagyva.");

                continue;
            }

            Mailbox::updateOrCreate(
                ['email' => $email],
                [
                    'name' => strstr($email, '@', true) ?: $email,
                    'username' => $email,
                    'password' => $password,
                    'imap_host' => self::SERVER_HOST,
                    'imap_port' => self::IMAP_PORT,
                    'imap_encryption' => MailEncryption::Ssl,
                    'smtp_host' => self::SERVER_HOST,
                    'smtp_port' => self::SMTP_PORT,
                    'smtp_encryption' => MailEncryption::Ssl,
                ],
            );
        }
    }
}

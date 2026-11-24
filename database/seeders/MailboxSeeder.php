<?php

namespace Database\Seeders;

use App\Enums\MailEncryption;
use App\Models\Mailbox;
use Illuminate\Database\Seeder;

class MailboxSeeder extends Seeder
{
    /**
     * Load the mailbox accounts from the credentials file into the mailboxes table.
     *
     * The file lists each account as an e-mail address line followed by its
     * password line, plus `IMAP: host` / `SMTP: host` sections with optional
     * `Port:` and `Titkosítás:` lines. Mailboxes are upserted by e-mail address,
     * so re-running the seeder refreshes credentials instead of duplicating rows.
     */
    public function run(): void
    {
        $path = (string) config('developer-tool.mailboxes_file');

        if (! is_file($path)) {
            $this->command?->warn("Postafiók hozzáférési fájl nem található: {$path}");

            return;
        }

        $lines = array_values(array_filter(
            array_map('trim', file($path) ?: []),
            fn (string $line): bool => $line !== '',
        ));

        $servers = $this->parseServers($lines);

        foreach ($this->parseAccounts($lines) as $email => $password) {
            Mailbox::updateOrCreate(
                ['email' => $email],
                [
                    'name' => strstr($email, '@', true) ?: $email,
                    'username' => $email,
                    'password' => $password,
                    'imap_host' => $servers['imap']['host'],
                    'imap_port' => $servers['imap']['port'],
                    'imap_encryption' => $servers['imap']['encryption'],
                    'smtp_host' => $servers['smtp']['host'],
                    'smtp_port' => $servers['smtp']['port'],
                    'smtp_encryption' => $servers['smtp']['encryption'],
                ],
            );
        }
    }

    /**
     * Every e-mail address line paired with the line that follows it (the password).
     *
     * @param  list<string>  $lines
     * @return array<string, string>
     */
    private function parseAccounts(array $lines): array
    {
        $accounts = [];

        foreach ($lines as $index => $line) {
            if (filter_var($line, FILTER_VALIDATE_EMAIL) !== false && isset($lines[$index + 1])) {
                $accounts[strtolower($line)] = $lines[$index + 1];
            }
        }

        return $accounts;
    }

    /**
     * The IMAP and SMTP settings; `Port:` / `Titkosítás:` lines belong to the
     * most recent `IMAP:` or `SMTP:` heading line.
     *
     * @param  list<string>  $lines
     * @return array{imap: array{host: string, port: int, encryption: MailEncryption}, smtp: array{host: string, port: int, encryption: MailEncryption}}
     */
    private function parseServers(array $lines): array
    {
        $servers = [
            'imap' => ['host' => '', 'port' => 993, 'encryption' => MailEncryption::Ssl],
            'smtp' => ['host' => '', 'port' => 465, 'encryption' => MailEncryption::Ssl],
        ];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^(IMAP|SMTP)\s*:\s*(\S+)/i', $line, $matches) === 1) {
                $current = strtoupper($matches[1]) === 'IMAP' ? 'imap' : 'smtp';
                $servers[$current]['host'] = $matches[2];
            } elseif ($current !== null && preg_match('/^Port\s*:\s*(\d+)/i', $line, $matches) === 1) {
                $servers[$current]['port'] = (int) $matches[1];
            } elseif ($current !== null && preg_match('/^(Titkosítás|Encryption)\s*:\s*(.+)$/iu', $line, $matches) === 1) {
                $servers[$current]['encryption'] = $this->parseEncryption($matches[2]);
            }
        }

        return $servers;
    }

    private function parseEncryption(string $value): MailEncryption
    {
        $value = strtoupper($value);

        return match (true) {
            str_contains($value, 'SSL') => MailEncryption::Ssl,
            str_contains($value, 'TLS') => MailEncryption::Tls,
            default => MailEncryption::None,
        };
    }
}

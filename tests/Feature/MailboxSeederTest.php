<?php

use App\Enums\MailEncryption;
use App\Models\Mailbox;
use Database\Seeders\MailboxSeeder;

beforeEach(function () {
    $this->mailboxesFile = sys_get_temp_dir().'/dev-tool-mail-'.uniqid().'.md';

    config(['developer-tool.mailboxes_file' => $this->mailboxesFile]);
});

afterEach(function () {
    @unlink($this->mailboxesFile);
});

function writeMailboxesFile(string $path): void
{
    file_put_contents($path, <<<'MD'
        agent1@example.test
        first-Secret
        agent2@example.test
        second-Secret


        Imap és smtp csatlakozások:

        IMAP: imap.example.test
        Port: 993
        Titkosítás: SSL / TLS

        SMTP: smtp.example.test
        Port: 587
        Titkosítás: STARTTLS
        MD);
}

test('it creates a mailbox for every account in the credentials file', function () {
    writeMailboxesFile($this->mailboxesFile);

    $this->seed(MailboxSeeder::class);

    expect(Mailbox::count())->toBe(2);

    $mailbox = Mailbox::where('email', 'agent2@example.test')->firstOrFail();

    expect($mailbox)
        ->name->toBe('agent2')
        ->username->toBe('agent2@example.test')
        ->password->toBe('second-Secret')
        ->imap_host->toBe('imap.example.test')
        ->imap_port->toBe(993)
        ->imap_encryption->toBe(MailEncryption::Ssl)
        ->smtp_host->toBe('smtp.example.test')
        ->smtp_port->toBe(587)
        ->smtp_encryption->toBe(MailEncryption::Tls);
});

test('re-running the seeder updates existing mailboxes instead of duplicating them', function () {
    Mailbox::factory()->create(['email' => 'agent1@example.test', 'password' => 'old', 'is_active' => false]);
    writeMailboxesFile($this->mailboxesFile);

    $this->seed(MailboxSeeder::class);
    $this->seed(MailboxSeeder::class);

    $mailbox = Mailbox::where('email', 'agent1@example.test')->sole();

    expect(Mailbox::count())->toBe(2)
        ->and($mailbox->password)->toBe('first-Secret')
        ->and($mailbox->is_active)->toBeFalse();
});

test('it skips seeding when the credentials file is missing', function () {
    $this->seed(MailboxSeeder::class);

    expect(Mailbox::count())->toBe(0);
});

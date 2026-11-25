<?php

use App\Enums\MailEncryption;
use App\Models\Mailbox;
use Database\Seeders\MailboxSeeder;

beforeEach(function () {
    config(['developer-tool.mailbox_passwords' => [
        'agent1@example.test' => 'first-Secret',
        'agent2@example.test' => 'second-Secret',
    ]]);
});

test('it creates a mailbox for every configured account with the imap and smtp settings', function () {
    $this->seed(MailboxSeeder::class);

    expect(Mailbox::count())->toBe(2);

    expect(Mailbox::where('email', 'agent2@example.test')->firstOrFail())
        ->name->toBe('agent2')
        ->username->toBe('agent2@example.test')
        ->password->toBe('second-Secret')
        ->imap_host->toBe('mail.dunahousemail.hu')
        ->imap_port->toBe(993)
        ->imap_encryption->toBe(MailEncryption::Ssl)
        ->smtp_host->toBe('mail.dunahousemail.hu')
        ->smtp_port->toBe(465)
        ->smtp_encryption->toBe(MailEncryption::Ssl);
});

test('re-running the seeder updates existing mailboxes instead of duplicating them', function () {
    Mailbox::factory()->create(['email' => 'agent1@example.test', 'password' => 'old', 'is_active' => false]);

    $this->seed(MailboxSeeder::class);
    $this->seed(MailboxSeeder::class);

    $mailbox = Mailbox::where('email', 'agent1@example.test')->sole();

    expect(Mailbox::count())->toBe(2)
        ->and($mailbox->password)->toBe('first-Secret')
        ->and($mailbox->is_active)->toBeFalse();
});

test('it skips an account without a password', function () {
    config(['developer-tool.mailbox_passwords' => [
        'agent1@example.test' => 'first-Secret',
        'agent2@example.test' => null,
    ]]);

    $this->seed(MailboxSeeder::class);

    expect(Mailbox::pluck('email')->all())->toBe(['agent1@example.test']);
});

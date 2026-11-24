<?php

use App\Enums\MailEncryption;
use App\Mail\MailboxMessage;
use App\Models\IncomingEmail;
use App\Models\Mailbox;
use App\Services\Mail\FetchedEmail;
use App\Services\Mail\ImapMailFetcher;
use App\Services\MailboxService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

function fetchedEmail(int $uid, array $overrides = []): FetchedEmail
{
    return new FetchedEmail(...[
        'uid' => $uid,
        'messageId' => "<{$uid}@example.test>",
        'fromAddress' => 'sender@example.test',
        'fromName' => 'Sender',
        'to' => ['agent@example.test'],
        'cc' => [],
        'subject' => "Message {$uid}",
        'bodyText' => 'Hello',
        'bodyHtml' => '<p>Hello</p>',
        'receivedAt' => CarbonImmutable::parse('2026-11-20 10:00:00'),
        ...$overrides,
    ]);
}

test('fetching stores new messages and only asks for UIDs after the last stored one', function () {
    $mailbox = Mailbox::factory()->create();
    IncomingEmail::factory()->for($mailbox)->create(['uid' => 7]);

    $this->mock(ImapMailFetcher::class)
        ->shouldReceive('fetch')
        ->once()
        ->withArgs(fn (Mailbox $given, int $afterUid) => $given->is($mailbox) && $afterUid === 7)
        ->andReturn([fetchedEmail(8), fetchedEmail(9, ['subject' => 'Árvíztűrő'])]);

    $created = app(MailboxService::class)->fetchIncoming($mailbox);

    expect($created)->toBe(2)
        ->and($mailbox->incomingEmails()->count())->toBe(3)
        ->and($mailbox->incomingEmails()->where('uid', 9)->value('subject'))->toBe('Árvíztűrő')
        ->and($mailbox->fresh()->last_fetched_at)->not->toBeNull();
});

test('fetching does not duplicate an already stored message', function () {
    $mailbox = Mailbox::factory()->create();
    IncomingEmail::factory()->for($mailbox)->create(['uid' => 5]);

    $this->mock(ImapMailFetcher::class)
        ->shouldReceive('fetch')
        ->andReturn([fetchedEmail(5)]);

    expect(app(MailboxService::class)->fetchIncoming($mailbox))->toBe(0)
        ->and($mailbox->incomingEmails()->count())->toBe(1);
});

test('sending uses a mailer built from the mailbox smtp settings', function () {
    Mail::fake();

    $mailbox = Mailbox::factory()->create([
        'email' => 'agent@example.test',
        'from_name' => 'Agent',
        'smtp_host' => 'smtp.example.test',
        'smtp_port' => 587,
        'smtp_encryption' => MailEncryption::Tls,
        'password' => 'smtp-secret',
    ]);

    app(MailboxService::class)->send($mailbox, 'someone@example.test', 'Hello', 'Body text');

    Mail::assertSent(MailboxMessage::class, function (MailboxMessage $mail) use ($mailbox) {
        return $mail->hasTo('someone@example.test')
            && $mail->hasFrom('agent@example.test', 'Agent')
            && $mail->hasSubject('Hello')
            && $mail->mailer === 'mailbox_'.$mailbox->id;
    });

    expect(config("mail.mailers.mailbox_{$mailbox->id}"))
        ->scheme->toBe('smtp')
        ->host->toBe('smtp.example.test')
        ->port->toBe(587)
        ->password->toBe('smtp-secret')
        ->require_tls->toBeTrue();
});

test('the mailbox message renders plain text and html bodies', function () {
    $mailbox = Mailbox::factory()->make();

    (new MailboxMessage($mailbox, 'Subject', "Line 1\nLine 2"))
        ->assertSeeInText('Line 2');

    (new MailboxMessage($mailbox, 'Subject', '<p>Hi <b>there</b></p>', isHtml: true))
        ->assertSeeInHtml('<b>there</b>', escape: false);
});

test('the imap reference reflects the encryption setting', function (MailEncryption $encryption, string $expected) {
    $mailbox = Mailbox::factory()->make([
        'imap_host' => 'mail.example.test',
        'imap_port' => 993,
        'imap_encryption' => $encryption,
        'imap_folder' => 'INBOX',
    ]);

    expect(app(ImapMailFetcher::class)->mailboxReference($mailbox))->toBe($expected);
})->with([
    [MailEncryption::Ssl, '{mail.example.test:993/imap/ssl}INBOX'],
    [MailEncryption::Tls, '{mail.example.test:993/imap/tls}INBOX'],
    [MailEncryption::None, '{mail.example.test:993/imap/notls}INBOX'],
]);

<?php

use App\Enums\MailEncryption;
use App\Enums\OutgoingEmailStatus;
use App\Jobs\RunClaudePrompt;
use App\Mail\MailboxMessage;
use App\Models\Agent;
use App\Models\Attachment;
use App\Models\IncomingEmail;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use App\Models\Project;
use App\Models\PromptRun;
use App\Services\Mail\FetchedAttachment;
use App\Services\Mail\FetchedEmail;
use App\Services\Mail\ImapMailFetcher;
use App\Services\MailboxService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

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

test('a deleted newest message is not downloaded again', function () {
    $mailbox = Mailbox::factory()->create();

    $fetcher = $this->mock(ImapMailFetcher::class);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->withArgs(fn (Mailbox $given, int $afterUid) => $afterUid === 0)
        ->andReturn([fetchedEmail(4), fetchedEmail(5)]);
    $fetcher->shouldReceive('fetch')
        ->once()
        ->withArgs(fn (Mailbox $given, int $afterUid) => $afterUid === 5)
        ->andReturn([]);

    $service = app(MailboxService::class);
    $service->fetchIncoming($mailbox);
    $mailbox->incomingEmails()->where('uid', 5)->delete();

    expect($service->fetchIncoming($mailbox->fresh()))->toBe(0)
        ->and($mailbox->fresh()->last_uid)->toBe(5);
});

test('fetching stores the attachments of a new message on the private disk', function () {
    Storage::fake('local');
    $mailbox = Mailbox::factory()->create();

    $this->mock(ImapMailFetcher::class)
        ->shouldReceive('fetch')
        ->andReturn([fetchedEmail(3, ['attachments' => [
            new FetchedAttachment('számla.pdf', 'application/pdf', '%PDF-1.4 content'),
            new FetchedAttachment('kep.png', 'image/png', 'png-bytes'),
        ]])]);

    app(MailboxService::class)->fetchIncoming($mailbox);

    $attachments = $mailbox->incomingEmails()->sole()->attachments()->orderBy('attachments.id')->get();

    expect($attachments)->toHaveCount(2)
        ->and($attachments[0])
        ->filename->toBe('számla.pdf')
        ->mime_type->toBe('application/pdf')
        ->size->toBe(16);

    Storage::disk('local')->assertExists($attachments[0]->path);
    expect(Storage::disk('local')->get($attachments[0]->path))->toBe('%PDF-1.4 content');
});

test('with automatic reading on, the agent reads every newly fetched message', function () {
    Queue::fake();
    $agent = Agent::factory()->create();
    $project = Project::factory()->create();
    $mailbox = Mailbox::factory()->for($agent)->for($project)->create(['auto_read' => true]);
    IncomingEmail::factory()->for($mailbox)->create(['uid' => 1]);

    $this->mock(ImapMailFetcher::class)
        ->shouldReceive('fetch')
        ->andReturn([fetchedEmail(2), fetchedEmail(3)]);

    app(MailboxService::class)->fetchIncoming($mailbox);

    expect($agent->promptRuns()->where('agent_contexts.project_id', $project->id)->count())->toBe(2)
        ->and($mailbox->incomingEmails()->whereNull('read_at')->pluck('uid')->all())->toBe([1]);

    Queue::assertPushed(RunClaudePrompt::class, 2);
});

test('with automatic reading off, fetched messages stay unread', function () {
    Queue::fake();
    $mailbox = Mailbox::factory()->for(Agent::factory())->for(Project::factory())->create(['auto_read' => false]);

    $this->mock(ImapMailFetcher::class)
        ->shouldReceive('fetch')
        ->andReturn([fetchedEmail(2)]);

    app(MailboxService::class)->fetchIncoming($mailbox);

    expect($mailbox->incomingEmails()->whereNull('read_at')->count())->toBe(1)
        ->and(PromptRun::count())->toBe(0);
});

test('the agent prompt lists the attachment files and falls back to the html body', function () {
    Storage::fake('local');
    $project = Project::factory()->create(['name' => 'webshop']);
    $mailbox = Mailbox::factory()->create(['name' => 'Support', 'email' => 'support@example.test', 'project_id' => $project->id]);
    $email = IncomingEmail::factory()->for($mailbox)->create([
        'from_name' => 'Kiss Anna',
        'from_address' => 'anna@example.test',
        'body_text' => null,
        'body_html' => '<p>Csak <b>HTML</b> törzs</p>',
    ]);
    $attachment = Attachment::factory()->create([
        'filename' => 'log.txt',
        'mime_type' => 'text/plain',
        'disk' => 'local',
        'path' => 'attachments/x/abc',
    ]);
    $email->attachments()->attach($attachment->id);

    $prompt = app(MailboxService::class)->agentPrompt($mailbox, $email);

    expect($prompt)->toContain(
        '„Support” (support@example.test)',
        '„webshop” projekten',
        'Feladó: Kiss Anna <anna@example.test>',
        '- log.txt (text/plain): '.Storage::disk('local')->path('attachments/x/abc'),
        'Csak HTML törzs',
    );
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

test('the agent prompt tells the agent how to reply to the message', function () {
    $mailbox = Mailbox::factory()->create(['email' => 'support@example.test']);
    $email = IncomingEmail::factory()->for($mailbox)->create();

    expect(app(MailboxService::class)->agentPrompt($mailbox, $email))
        ->toContain("`reply-email` eszközzel tedd meg (incoming_email_id: {$email->id})")
        ->toContain('support@example.test címről');
});

test('without automatic replies a reply is stored as a draft to the sender', function () {
    Mail::fake();
    $email = IncomingEmail::factory()->create([
        'from_address' => 'customer@example.test',
        'subject' => 'Hibás számla',
        'message_id' => '<abc@example.test>',
    ]);
    $run = PromptRun::factory()->create();

    $reply = app(MailboxService::class)->reply($email, 'Javítottuk.', $run);

    expect($reply->fresh())
        ->mailbox_id->toBe($email->mailbox_id)
        ->prompt_run_id->toBe($run->id)
        ->to->toBe(['customer@example.test'])
        ->subject->toBe('Re: Hibás számla')
        ->body_text->toBe('Javítottuk.')
        ->in_reply_to->toBe('<abc@example.test>')
        ->status->toBe(OutgoingEmailStatus::Draft);

    Mail::assertNothingSent();
});

test('a reply subject keeps an existing re prefix and fills in a missing subject', function (?string $subject, string $expected) {
    $email = IncomingEmail::factory()->create(['subject' => $subject]);

    expect(app(MailboxService::class)->reply($email, 'Body')->subject)->toBe($expected);
})->with([
    'existing prefix' => ['RE: Kérdés', 'RE: Kérdés'],
    'missing subject' => [null, 'Re: (nincs tárgy)'],
]);

test('with automatic replies the reply is sent right away from the mailbox', function () {
    Mail::fake();
    $mailbox = Mailbox::factory()->create(['auto_reply' => true, 'email' => 'support@example.test']);
    $email = IncomingEmail::factory()->for($mailbox)->create(['from_address' => 'customer@example.test']);

    $reply = app(MailboxService::class)->reply($email, 'Javítottuk.');

    expect($reply->fresh())
        ->status->toBe(OutgoingEmailStatus::Sent)
        ->sent_at->not->toBeNull();

    Mail::assertSent(MailboxMessage::class, fn (MailboxMessage $mail) => $mail->hasTo('customer@example.test')
        && $mail->hasFrom('support@example.test')
        && $mail->mailer === 'mailbox_'.$mailbox->id);
});

test('a message can be answered only once', function () {
    $email = IncomingEmail::factory()->create();
    app(MailboxService::class)->reply($email, 'Első válasz');

    expect(fn () => app(MailboxService::class)->reply($email, 'Második válasz'))
        ->toThrow(RuntimeException::class, 'Erre a levélre már született válasz.');

    expect(OutgoingEmail::count())->toBe(1);
});

test('a message without a sender cannot be answered', function () {
    $email = IncomingEmail::factory()->create(['from_address' => null]);

    expect(fn () => app(MailboxService::class)->reply($email, 'Válasz'))
        ->toThrow(RuntimeException::class, 'A levélnek nincs feladója');

    expect(OutgoingEmail::count())->toBe(0);
});

test('a failed send is recorded on the reply instead of being thrown', function () {
    Mail::shouldReceive('purge');
    Mail::shouldReceive('mailer')->andThrow(new RuntimeException('Connection refused'));
    $reply = OutgoingEmail::factory()->create();

    app(MailboxService::class)->sendOutgoing($reply);

    expect($reply->fresh())
        ->status->toBe(OutgoingEmailStatus::Failed)
        ->error->toBe('Connection refused')
        ->sent_at->toBeNull();
});

test('a sent reply is not sent again', function () {
    Mail::fake();
    $reply = OutgoingEmail::factory()->sent()->create();

    expect(fn () => app(MailboxService::class)->sendOutgoing($reply))
        ->toThrow(RuntimeException::class, 'Ez a levél már el lett küldve.');

    Mail::assertNothingSent();
});

test('a reply carries the threading headers of the answered message', function () {
    $mailbox = Mailbox::factory()->make();

    Mail::mailer('array')->to('customer@example.test')
        ->send(new MailboxMessage($mailbox, 'Re: Kérdés', 'Válasz', inReplyTo: '<abc@example.test>'));

    $headers = Mail::mailer('array')->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getHeaders();

    expect($headers->get('In-Reply-To')->getBodyAsString())->toBe('<abc@example.test>')
        ->and($headers->get('References')->getBodyAsString())->toBe('<abc@example.test>');
});

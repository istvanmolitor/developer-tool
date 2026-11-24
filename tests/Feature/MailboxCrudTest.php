<?php

use App\Enums\MailEncryption;
use App\Models\IncomingEmail;
use App\Models\IncomingEmailAttachment;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\MailboxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array<string, mixed>
 */
function mailboxPayload(array $overrides = []): array
{
    return [
        'name' => 'AI agent 1',
        'email' => 'agent1@example.test',
        'from_name' => 'AI Agent',
        'username' => 'agent1@example.test',
        'password' => 'secret-password',
        'imap_host' => 'imap.example.test',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_folder' => 'INBOX',
        'smtp_host' => 'smtp.example.test',
        'smtp_port' => 465,
        'smtp_encryption' => 'ssl',
        'is_active' => true,
        ...$overrides,
    ];
}

test('guests are redirected from the mailboxes index', function () {
    $this->get(route('mailboxes.index'))->assertRedirect(route('login'));
});

test('the mailboxes index lists every mailbox without exposing passwords', function () {
    Mailbox::factory()->count(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('mailboxes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Mailboxes/Index')
            ->has('mailboxes', 2)
            ->missing('mailboxes.0.password')
        );
});

test('a mailbox can be viewed with its incoming emails', function () {
    $mailbox = Mailbox::factory()->create();
    IncomingEmail::factory()->count(3)->for($mailbox)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('mailboxes.show', $mailbox))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Mailboxes/Show')
            ->where('mailbox.id', $mailbox->id)
            ->missing('mailbox.password')
            ->has('incomingEmails', 3)
            ->where('incomingEmailsCount', 3)
        );
});

test('a mailbox can be created and its password is stored encrypted', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('mailboxes.store'), mailboxPayload())
        ->assertRedirect(route('mailboxes.index'));

    $mailbox = Mailbox::where('name', 'AI agent 1')->firstOrFail();

    expect($mailbox->password)->toBe('secret-password')
        ->and($mailbox->imap_encryption)->toBe(MailEncryption::Ssl)
        ->and(DB::table('mailboxes')->value('password'))->not->toBe('secret-password');
});

test('a mailbox requires its connection data', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('mailboxes.store'), [])
        ->assertSessionHasErrors(['name', 'email', 'username', 'password', 'imap_host', 'imap_port', 'smtp_host', 'smtp_port']);
});

test('a mailbox rejects an unknown encryption and a duplicate name', function () {
    Mailbox::factory()->create(['name' => 'AI agent 1']);

    $this->actingAs(User::factory()->create())
        ->post(route('mailboxes.store'), mailboxPayload(['imap_encryption' => 'rot13']))
        ->assertSessionHasErrors(['name', 'imap_encryption']);
});

test('updating a mailbox with an empty password keeps the stored one', function () {
    $mailbox = Mailbox::factory()->create(['password' => 'original']);

    $this->actingAs(User::factory()->create())
        ->put(route('mailboxes.update', $mailbox), mailboxPayload(['password' => '', 'smtp_port' => 587, 'smtp_encryption' => 'tls']))
        ->assertRedirect(route('mailboxes.show', $mailbox));

    expect($mailbox->fresh())
        ->password->toBe('original')
        ->smtp_port->toBe(587)
        ->smtp_encryption->toBe(MailEncryption::Tls);
});

test('updating a mailbox with a new password replaces it', function () {
    $mailbox = Mailbox::factory()->create(['password' => 'original']);

    $this->actingAs(User::factory()->create())
        ->put(route('mailboxes.update', $mailbox), mailboxPayload(['password' => 'changed']))
        ->assertRedirect();

    expect($mailbox->fresh()->password)->toBe('changed');
});

test('deleting a mailbox removes its incoming emails', function () {
    $mailbox = Mailbox::factory()->create();
    IncomingEmail::factory()->for($mailbox)->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('mailboxes.destroy', $mailbox))
        ->assertRedirect(route('mailboxes.index'));

    $this->assertDatabaseMissing('mailboxes', ['id' => $mailbox->id]);
    $this->assertDatabaseCount('incoming_emails', 0);
});

test('an incoming email can be deleted from its mailbox', function () {
    $mailbox = Mailbox::factory()->create();
    $email = IncomingEmail::factory()->for($mailbox)->create();
    $other = IncomingEmail::factory()->for($mailbox)->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('mailboxes.emails.destroy', [$mailbox, $email]))
        ->assertRedirect(route('mailboxes.show', $mailbox));

    $this->assertModelMissing($email);
    $this->assertModelExists($other);
});

test('an incoming email cannot be deleted through another mailbox', function () {
    $email = IncomingEmail::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('mailboxes.emails.destroy', [Mailbox::factory()->create(), $email]))
        ->assertNotFound();

    $this->assertModelExists($email);
});

test('opening an incoming email marks it as read once', function () {
    $mailbox = Mailbox::factory()->create();
    $email = IncomingEmail::factory()->for($mailbox)->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('mailboxes.emails.read', [$mailbox, $email]))
        ->assertRedirect(route('mailboxes.show', $mailbox));

    $firstReadAt = $email->fresh()->read_at;
    expect($firstReadAt)->not->toBeNull();

    $this->travel(1)->hour();
    $this->actingAs($user)->post(route('mailboxes.emails.read', [$mailbox, $email]));

    expect($email->fresh()->read_at->equalTo($firstReadAt))->toBeTrue();
});

test('an incoming email cannot be marked as read through another mailbox', function () {
    $email = IncomingEmail::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('mailboxes.emails.read', [Mailbox::factory()->create(), $email]))
        ->assertNotFound();

    expect($email->fresh()->read_at)->toBeNull();
});

test('the mailbox pages report the unread email counts', function () {
    $mailbox = Mailbox::factory()->create();
    IncomingEmail::factory()->for($mailbox)->count(2)->create();
    IncomingEmail::factory()->for($mailbox)->read()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('mailboxes.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mailboxes.0.incoming_emails_count', 3)
            ->where('mailboxes.0.unread_emails_count', 2));

    $this->actingAs($user)
        ->get(route('mailboxes.show', $mailbox))
        ->assertInertia(fn (Assert $page) => $page
            ->where('unreadEmailsCount', 2)
            ->has('incomingEmails.0.read_at'));
});

/**
 * Create an attachment for the email with its file written to the (faked) private disk.
 */
function storedAttachment(IncomingEmail $email, string $content = 'file content'): IncomingEmailAttachment
{
    $path = $email->attachmentsDirectory().'/stored-file';
    Storage::disk('local')->put($path, $content);

    return IncomingEmailAttachment::factory()->for($email)->create([
        'filename' => 'jelentés.pdf',
        'mime_type' => 'application/pdf',
        'size' => strlen($content),
        'path' => $path,
    ]);
}

test('an attachment can be downloaded with its original file name', function () {
    Storage::fake('local');
    $email = IncomingEmail::factory()->create();
    $attachment = storedAttachment($email, '%PDF-1.4');

    $response = $this->actingAs(User::factory()->create())
        ->get(route('mailboxes.emails.attachments.download', [$email->mailbox, $email, $attachment]))
        ->assertOk()
        ->assertDownload('jelentes.pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))->toContain("filename*=utf-8''jelent%C3%A9s.pdf")
        ->and($response->streamedContent())->toBe('%PDF-1.4');
});

test('an attachment cannot be downloaded through another email', function () {
    Storage::fake('local');
    $email = IncomingEmail::factory()->create();
    $otherEmail = IncomingEmail::factory()->for($email->mailbox)->create();
    $attachment = storedAttachment($email);

    $this->actingAs(User::factory()->create())
        ->get(route('mailboxes.emails.attachments.download', [$email->mailbox, $otherEmail, $attachment]))
        ->assertNotFound();
});

test('guests cannot download attachments', function () {
    Storage::fake('local');
    $email = IncomingEmail::factory()->create();
    $attachment = storedAttachment($email);

    $this->get(route('mailboxes.emails.attachments.download', [$email->mailbox, $email, $attachment]))
        ->assertRedirect(route('login'));
});

test('deleting an email removes its attachment files', function () {
    Storage::fake('local');
    $email = IncomingEmail::factory()->create();
    $attachment = storedAttachment($email);

    $this->actingAs(User::factory()->create())
        ->delete(route('mailboxes.emails.destroy', [$email->mailbox, $email]));

    $this->assertModelMissing($attachment);
    Storage::disk('local')->assertMissing($attachment->path);
});

test('deleting a mailbox removes the attachment files of its emails', function () {
    Storage::fake('local');
    $email = IncomingEmail::factory()->create();
    $attachment = storedAttachment($email);

    $this->actingAs(User::factory()->create())
        ->delete(route('mailboxes.destroy', $email->mailbox));

    $this->assertModelMissing($attachment);
    Storage::disk('local')->assertMissing($attachment->path);
});

test('the mailbox page lists the attachments of each email', function () {
    $email = IncomingEmail::factory()->create();
    IncomingEmailAttachment::factory()->for($email)->create(['filename' => 'terv.docx']);

    $this->actingAs(User::factory()->create())
        ->get(route('mailboxes.show', $email->mailbox))
        ->assertInertia(fn (Assert $page) => $page
            ->where('incomingEmails.0.attachments.0.filename', 'terv.docx')
            ->missing('incomingEmails.0.attachments.0.path'));
});

test('the fetch action downloads mail through the service', function () {
    $mailbox = Mailbox::factory()->create();

    $this->mock(MailboxService::class)
        ->shouldReceive('fetchIncoming')
        ->once()
        ->withArgs(fn (Mailbox $given) => $given->is($mailbox))
        ->andReturn(2);

    $this->actingAs(User::factory()->create())
        ->post(route('mailboxes.fetch', $mailbox))
        ->assertRedirect(route('mailboxes.show', $mailbox));
});

test('the fetch action reports a connection failure instead of crashing', function () {
    $mailbox = Mailbox::factory()->create();

    $this->mock(MailboxService::class)
        ->shouldReceive('fetchIncoming')
        ->once()
        ->andThrow(new RuntimeException('connection refused'));

    $this->actingAs(User::factory()->create())
        ->post(route('mailboxes.fetch', $mailbox))
        ->assertRedirect(route('mailboxes.show', $mailbox));
});

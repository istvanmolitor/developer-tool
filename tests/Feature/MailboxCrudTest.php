<?php

use App\Enums\MailEncryption;
use App\Models\IncomingEmail;
use App\Models\Mailbox;
use App\Models\User;
use App\Services\MailboxService;
use Illuminate\Support\Facades\DB;
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

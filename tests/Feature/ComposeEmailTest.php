<?php

use App\Enums\OutgoingEmailStatus;
use App\Mail\MailboxMessage;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
});

test('the compose page can be opened', function () {
    $mailbox = Mailbox::factory()->create();

    $this->actingAs($this->user)
        ->get(route('mailboxes.compose', $mailbox))
        ->assertOk();
});

test('composing sends a new mail from the mailbox', function () {
    $mailbox = Mailbox::factory()->create();

    $this->actingAs($this->user)
        ->post(route('mailboxes.compose.store', $mailbox), [
            'to' => 'customer@example.test, second@example.test',
            'subject' => 'Ajánlat',
            'body' => 'Köszönjük a megkeresést.',
        ])
        ->assertRedirect(route('mailboxes.outgoing', $mailbox));

    $outgoing = OutgoingEmail::sole();

    expect($outgoing)
        ->mailbox_id->toBe($mailbox->id)
        ->incoming_email_id->toBeNull()
        ->to->toBe(['customer@example.test', 'second@example.test'])
        ->subject->toBe('Ajánlat')
        ->status->toBe(OutgoingEmailStatus::Sent);

    Mail::assertSent(
        MailboxMessage::class,
        fn (MailboxMessage $mail): bool => $mail->hasTo('customer@example.test') && $mail->hasTo('second@example.test'),
    );
});

test('composing attaches uploaded files to the mail', function () {
    Storage::fake('local');
    // A mailbox with no project stores its attachments on the private disk.
    $mailbox = Mailbox::factory()->create();

    $this->actingAs($this->user)
        ->post(route('mailboxes.compose.store', $mailbox), [
            'to' => ['customer@example.test'],
            'subject' => 'Számla',
            'body' => 'Mellékelve.',
            'attachments' => [UploadedFile::fake()->create('szamla.pdf', 10, 'application/pdf')],
        ])
        ->assertRedirect(route('mailboxes.outgoing', $mailbox));

    $outgoing = OutgoingEmail::sole();

    expect($outgoing->attachments)->toHaveCount(1);
    expect($outgoing->attachments->first()->filename)->toBe('szamla.pdf');

    Mail::assertSent(
        MailboxMessage::class,
        fn (MailboxMessage $mail): bool => count($mail->files) === 1,
    );
});

test('the outgoing page lists outgoing mails', function () {
    $outgoing = OutgoingEmail::factory()->sent()->create([
        'to' => ['customer@example.test'],
        'subject' => 'Visszaigazolás',
    ]);

    $this->actingAs($this->user)
        ->get(route('mailboxes.outgoing', $outgoing->mailbox))
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Mailboxes/Outgoing')
                ->where('outgoingEmailsCount', 1)
                ->has('outgoingEmails.data', 1)
                ->where('outgoingEmails.data.0.subject', 'Visszaigazolás'),
        );
});

test('composing requires a valid recipient', function () {
    $mailbox = Mailbox::factory()->create();

    $this->actingAs($this->user)
        ->post(route('mailboxes.compose.store', $mailbox), [
            'to' => 'not-an-email',
            'subject' => 'Tárgy',
            'body' => 'Szöveg',
        ])
        ->assertSessionHasErrors('to.0');

    expect(OutgoingEmail::count())->toBe(0);
    Mail::assertNothingSent();
});

test('guests cannot compose', function () {
    $mailbox = Mailbox::factory()->create();

    $this->post(route('mailboxes.compose.store', $mailbox), [
        'to' => 'customer@example.test',
        'subject' => 'Tárgy',
        'body' => 'Szöveg',
    ])->assertRedirect(route('login'));

    Mail::assertNothingSent();
});

<?php

use App\Enums\OutgoingEmailStatus;
use App\Mail\MailboxMessage;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->user = User::factory()->create();
});

test('approving a draft reply sends it from the mailbox', function () {
    $reply = OutgoingEmail::factory()->create(['to' => ['customer@example.test']]);

    $this->actingAs($this->user)
        ->post(route('mailboxes.replies.send', [$reply->mailbox, $reply]))
        ->assertRedirect(route('mailboxes.outgoing', $reply->mailbox));

    expect($reply->fresh()->status)->toBe(OutgoingEmailStatus::Sent);
    Mail::assertSent(MailboxMessage::class, fn (MailboxMessage $mail) => $mail->hasTo('customer@example.test'));
});

test('a failed reply can be sent again', function () {
    $reply = OutgoingEmail::factory()->failed()->create();

    $this->actingAs($this->user)
        ->post(route('mailboxes.replies.send', [$reply->mailbox, $reply]));

    expect($reply->fresh())
        ->status->toBe(OutgoingEmailStatus::Sent)
        ->error->toBeNull();
});

test('a sent reply is not sent again', function () {
    $reply = OutgoingEmail::factory()->sent()->create();

    $this->actingAs($this->user)
        ->post(route('mailboxes.replies.send', [$reply->mailbox, $reply]))
        ->assertRedirect(route('mailboxes.outgoing', $reply->mailbox));

    Mail::assertNothingSent();
});

test('a draft reply can be discarded', function () {
    $reply = OutgoingEmail::factory()->create();

    $this->actingAs($this->user)
        ->delete(route('mailboxes.replies.destroy', [$reply->mailbox, $reply]))
        ->assertRedirect(route('mailboxes.outgoing', $reply->mailbox));

    expect(OutgoingEmail::count())->toBe(0);
});

test('a sent reply cannot be discarded', function () {
    $reply = OutgoingEmail::factory()->sent()->create();

    $this->actingAs($this->user)
        ->delete(route('mailboxes.replies.destroy', [$reply->mailbox, $reply]));

    expect($reply->fresh())->not->toBeNull();
});

test('a reply cannot be sent or discarded through another mailbox', function () {
    $reply = OutgoingEmail::factory()->create();
    $otherMailbox = Mailbox::factory()->create();

    $this->actingAs($this->user)
        ->post(route('mailboxes.replies.send', [$otherMailbox, $reply]))
        ->assertNotFound();

    $this->actingAs($this->user)
        ->delete(route('mailboxes.replies.destroy', [$otherMailbox, $reply]))
        ->assertNotFound();

    expect($reply->fresh()->status)->toBe(OutgoingEmailStatus::Draft);
    Mail::assertNothingSent();
});

test('guests cannot send replies', function () {
    $reply = OutgoingEmail::factory()->create();

    $this->post(route('mailboxes.replies.send', [$reply->mailbox, $reply]))
        ->assertRedirect(route('login'));

    Mail::assertNothingSent();
});

<?php

use App\Enums\OutgoingEmailStatus;
use App\Mail\MailboxMessage;
use App\Mcp\Servers\MailServer;
use App\Mcp\Tools\ReplyEmailTool;
use App\Models\IncomingEmail;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use App\Models\PromptRun;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->run = PromptRun::factory()->create();
    $this->email = IncomingEmail::factory()->create([
        'from_address' => 'customer@example.test',
        'prompt_run_id' => $this->run->id,
    ]);
});

afterEach(function () {
    putenv(MailServer::PROMPT_RUN_ENV);
});

function startMailServerFor(PromptRun $run): void
{
    putenv(MailServer::PROMPT_RUN_ENV.'='.$run->id);
}

test('reply-email stores a draft reply to the message handed to the run', function () {
    startMailServerFor($this->run);

    MailServer::tool(ReplyEmailTool::class, [
        'incoming_email_id' => $this->email->id,
        'body' => 'Javítottuk a hibát.',
    ])->assertOk()->assertSee('piszkozatként');

    expect(OutgoingEmail::sole())
        ->incoming_email_id->toBe($this->email->id)
        ->prompt_run_id->toBe($this->run->id)
        ->to->toBe(['customer@example.test'])
        ->body_text->toBe('Javítottuk a hibát.')
        ->status->toBe(OutgoingEmailStatus::Draft);

    Mail::assertNothingSent();
});

test('reply-email sends the reply right away when the mailbox replies automatically', function () {
    $this->email->mailbox->update(['auto_reply' => true]);
    startMailServerFor($this->run);

    MailServer::tool(ReplyEmailTool::class, [
        'incoming_email_id' => $this->email->id,
        'body' => 'Javítottuk a hibát.',
    ])->assertOk()->assertSee('A válasz elküldve: customer@example.test.');

    Mail::assertSent(MailboxMessage::class, fn (MailboxMessage $mail) => $mail->hasTo('customer@example.test'));
});

test('reply-email refuses a message that was not handed to the run', function () {
    $otherEmail = IncomingEmail::factory()->for(Mailbox::factory())->create([
        'prompt_run_id' => PromptRun::factory()->create()->id,
    ]);
    startMailServerFor($this->run);

    MailServer::tool(ReplyEmailTool::class, [
        'incoming_email_id' => $otherEmail->id,
        'body' => 'Válasz',
    ])->assertHasErrors(["A(z) {$otherEmail->id} azonosítójú levelet nem ebben a futásban kaptad"]);

    expect(OutgoingEmail::count())->toBe(0);
});

test('reply-email refuses to work outside an e-mail run', function () {
    MailServer::tool(ReplyEmailTool::class, [
        'incoming_email_id' => $this->email->id,
        'body' => 'Válasz',
    ])->assertHasErrors(['csak egy levelet feldolgozó agent-futásból használható']);

    expect(OutgoingEmail::count())->toBe(0);
});

test('reply-email reports that a message was already answered', function () {
    OutgoingEmail::factory()->create(['incoming_email_id' => $this->email->id, 'mailbox_id' => $this->email->mailbox_id]);
    startMailServerFor($this->run);

    MailServer::tool(ReplyEmailTool::class, [
        'incoming_email_id' => $this->email->id,
        'body' => 'Válasz',
    ])->assertHasErrors(['Erre a levélre már született válasz.']);

    expect(OutgoingEmail::count())->toBe(1);
});

<?php

use App\Enums\OutgoingEmailStatus;
use App\Mail\MailboxMessage;
use App\Mcp\Servers\MailServer;
use App\Mcp\Tools\ReplyEmailTool;
use App\Mcp\Tools\SendEmailTool;
use App\Models\Agent;
use App\Models\Attachment;
use App\Models\IncomingEmail;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use App\Models\Project;
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

/**
 * A prompt run acting as a fresh agent on a fresh project.
 */
function runForAgent(Agent $agent, Project $project): PromptRun
{
    return PromptRun::factory()
        ->inContext(['agent_id' => $agent->id, 'project_id' => $project->id])
        ->create();
}

test('send-email sends a new mail from the mailbox assigned to the agent', function () {
    $agent = Agent::factory()->create();
    $project = Project::factory()->create();
    $run = runForAgent($agent, $project);
    $mailbox = Mailbox::factory()->create([
        'agent_id' => $agent->id,
        'project_id' => $project->id,
        'auto_reply' => true,
    ]);
    startMailServerFor($run);

    MailServer::tool(SendEmailTool::class, [
        'to' => ['customer@example.test'],
        'subject' => 'Hírlevél',
        'body' => 'Üdvözlünk!',
    ])->assertOk()->assertSee('elküldve');

    expect(OutgoingEmail::sole())
        ->mailbox_id->toBe($mailbox->id)
        ->incoming_email_id->toBeNull()
        ->prompt_run_id->toBe($run->id)
        ->to->toBe(['customer@example.test'])
        ->status->toBe(OutgoingEmailStatus::Sent);

    Mail::assertSent(MailboxMessage::class, fn (MailboxMessage $mail) => $mail->hasTo('customer@example.test'));
});

test('send-email leaves a draft when the mailbox does not send automatically', function () {
    $agent = Agent::factory()->create();
    $project = Project::factory()->create();
    $run = runForAgent($agent, $project);
    Mailbox::factory()->create(['agent_id' => $agent->id, 'project_id' => $project->id, 'auto_reply' => false]);
    startMailServerFor($run);

    MailServer::tool(SendEmailTool::class, [
        'to' => ['customer@example.test'],
        'subject' => 'Hírlevél',
        'body' => 'Üdvözlünk!',
    ])->assertOk()->assertSee('piszkozat');

    expect(OutgoingEmail::sole()->status)->toBe(OutgoingEmailStatus::Draft);
    Mail::assertNothingSent();
});

test('send-email refuses an agent that has no mailbox', function () {
    $run = runForAgent(Agent::factory()->create(), Project::factory()->create());
    startMailServerFor($run);

    MailServer::tool(SendEmailTool::class, [
        'to' => ['customer@example.test'],
        'subject' => 'Hírlevél',
        'body' => 'Üdvözlünk!',
    ])->assertHasErrors(['Nincs hozzád rendelve postafiók']);

    expect(OutgoingEmail::count())->toBe(0);
    Mail::assertNothingSent();
});

test('send-email cannot send through a mailbox assigned to another agent', function () {
    $agent = Agent::factory()->create();
    $project = Project::factory()->create();
    $run = runForAgent($agent, $project);
    Mailbox::factory()->create(['agent_id' => $agent->id, 'project_id' => $project->id]);

    // A mailbox that belongs to a different agent must be unreachable.
    $foreign = Mailbox::factory()->create(['agent_id' => Agent::factory()->create()->id]);
    startMailServerFor($run);

    MailServer::tool(SendEmailTool::class, [
        'to' => ['customer@example.test'],
        'subject' => 'Hírlevél',
        'body' => 'Üdvözlünk!',
        'from' => $foreign->email,
    ])->assertHasErrors(['nem a hozzád rendelt postafiókok egyike']);

    expect(OutgoingEmail::count())->toBe(0);
    Mail::assertNothingSent();
});

test('send-email asks which mailbox when several are assigned', function () {
    $agent = Agent::factory()->create();
    $project = Project::factory()->create();
    $run = runForAgent($agent, $project);
    Mailbox::factory()->count(2)->create(['agent_id' => $agent->id, 'project_id' => $project->id]);
    startMailServerFor($run);

    MailServer::tool(SendEmailTool::class, [
        'to' => ['customer@example.test'],
        'subject' => 'Hírlevél',
        'body' => 'Üdvözlünk!',
    ])->assertHasErrors(['Több postafiók']);

    expect(OutgoingEmail::count())->toBe(0);
});

test('send-email rejects an attachment from another project', function () {
    $agent = Agent::factory()->create();
    $project = Project::factory()->create();
    $run = runForAgent($agent, $project);
    Mailbox::factory()->create(['agent_id' => $agent->id, 'project_id' => $project->id, 'auto_reply' => true]);

    $foreignAttachment = Attachment::factory()->create(['project_id' => Project::factory()->create()->id]);
    startMailServerFor($run);

    MailServer::tool(SendEmailTool::class, [
        'to' => ['customer@example.test'],
        'subject' => 'Hírlevél',
        'body' => 'Üdvözlünk!',
        'attachment_ids' => [$foreignAttachment->id],
    ])->assertHasErrors(['másik projekthez tartozik']);

    expect(OutgoingEmail::count())->toBe(0);
    Mail::assertNothingSent();
});

test('send-email refuses to work outside an e-mail run', function () {
    MailServer::tool(SendEmailTool::class, [
        'to' => ['customer@example.test'],
        'subject' => 'Hírlevél',
        'body' => 'Üdvözlünk!',
    ])->assertHasErrors(['csak egy agent-futásból használható']);

    expect(OutgoingEmail::count())->toBe(0);
});

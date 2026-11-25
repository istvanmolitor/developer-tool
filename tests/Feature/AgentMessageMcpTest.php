<?php

use App\Mcp\Servers\MessageServer;
use App\Mcp\Tools\ListMessagesTool;
use App\Mcp\Tools\SendMessageTool;
use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\Project;
use App\Models\PromptRun;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'alpha']);
    $this->backend = Agent::factory()->create(['name' => 'backend-developer']);
    $this->tester = Agent::factory()->create(['name' => 'tester']);
});

afterEach(function () {
    putenv(MessageServer::PROMPT_RUN_ENV);
});

function startMessageServerAs(Agent $agent, Project $project): PromptRun
{
    $run = PromptRun::factory()->inContext(['agent_id' => $agent->id, 'project_id' => $project->id])->create();
    putenv(MessageServer::PROMPT_RUN_ENV.'='.$run->id);

    return $run;
}

test('send-message stores an unread message from the run agent in the run project', function () {
    startMessageServerAs($this->backend, $this->project);

    MessageServer::tool(SendMessageTool::class, [
        'to' => 'tester',
        'body' => 'Kész az API, tesztelhető.',
    ])->assertOk()->assertSee('Üzenet elküldve neki: tester.');

    expect(AgentMessage::sole())
        ->project_id->toBe($this->project->id)
        ->sender_agent_id->toBe($this->backend->id)
        ->recipient_agent_id->toBe($this->tester->id)
        ->body->toBe('Kész az API, tesztelhető.')
        ->is_read->toBeFalse();
});

test('a run agent cannot send a message in the name of another agent', function () {
    startMessageServerAs($this->backend, $this->project);

    MessageServer::tool(SendMessageTool::class, [
        'to' => 'tester',
        'body' => 'Szia',
        'agent' => 'tester',
    ])->assertOk();

    expect(AgentMessage::sole()->sender_agent_id)->toBe($this->backend->id);
});

test('send-message refuses an unknown recipient and the sender itself', function () {
    startMessageServerAs($this->backend, $this->project);

    MessageServer::tool(SendMessageTool::class, ['to' => 'nincs-ilyen', 'body' => 'Szia'])
        ->assertHasErrors(['Nincs „nincs-ilyen” nevű agent']);

    MessageServer::tool(SendMessageTool::class, ['to' => 'backend-developer', 'body' => 'Szia'])
        ->assertHasErrors(['Saját magadnak nem küldhetsz üzenetet.']);

    expect(AgentMessage::count())->toBe(0);
});

test('outside a run the project and the acting agent must be named', function () {
    MessageServer::tool(SendMessageTool::class, ['to' => 'tester', 'body' => 'Szia'])
        ->assertHasErrors(['add meg a `project` és az `agent` paramétert']);

    MessageServer::tool(SendMessageTool::class, [
        'to' => 'tester',
        'body' => 'Szia',
        'project' => 'alpha',
        'agent' => 'backend-developer',
    ])->assertOk();

    expect(AgentMessage::sole()->sender_agent_id)->toBe($this->backend->id);
});

test('list-messages returns the unread messages of the run agent in its project and marks them read', function () {
    $otherProject = Project::factory()->create();
    $unread = AgentMessage::factory()->for($this->project)->create([
        'sender_agent_id' => $this->backend->id,
        'recipient_agent_id' => $this->tester->id,
        'body' => 'Új üzenet',
    ]);
    AgentMessage::factory()->read()->for($this->project)->create(['recipient_agent_id' => $this->tester->id, 'body' => 'Régi üzenet']);
    AgentMessage::factory()->for($otherProject)->create(['recipient_agent_id' => $this->tester->id, 'body' => 'Másik projekt']);
    AgentMessage::factory()->for($this->project)->create(['recipient_agent_id' => $this->backend->id, 'body' => 'Másnak szól']);

    startMessageServerAs($this->tester, $this->project);

    MessageServer::tool(ListMessagesTool::class)
        ->assertOk()
        ->assertSee(['Új üzenet', '"from":"backend-developer"', '"is_read":false'])
        ->assertDontSee(['Régi üzenet', 'Másik projekt', 'Másnak szól']);

    expect($unread->fresh())
        ->is_read->toBeTrue()
        ->read_at->not->toBeNull();

    MessageServer::tool(ListMessagesTool::class)
        ->assertOk()
        ->assertSee('"count":0')
        ->assertDontSee('Új üzenet');
});

test('list-messages can include already read messages', function () {
    AgentMessage::factory()->read()->for($this->project)->create(['recipient_agent_id' => $this->tester->id, 'body' => 'Régi üzenet']);

    startMessageServerAs($this->tester, $this->project);

    MessageServer::tool(ListMessagesTool::class, ['unread_only' => false])
        ->assertOk()
        ->assertSee('Régi üzenet');
});

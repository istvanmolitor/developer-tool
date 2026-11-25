<?php

use App\Models\Agent;
use App\Models\AgentContext;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('guests cannot start a new context', function () {
    $context = AgentContext::factory()->create();

    $this->post(route('agent-contexts.store', $context))->assertRedirect(route('login'));

    expect(AgentContext::count())->toBe(1);
});

test('a new context starts from an existing one with a fresh session', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create();
    $agent = Agent::factory()->create();
    $context = AgentContext::factory()->create([
        'project_id' => $project->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'session_id' => 'session-abc',
    ]);

    $this->actingAs($user)
        ->post(route('agent-contexts.store', $context))
        ->assertRedirect()
        ->assertSessionHas('status', 'Új beszélgetés indult.');

    expect(AgentContext::latest('id')->first())
        ->id->not->toBe($context->id)
        ->project_id->toBe($project->id)
        ->agent_id->toBe($agent->id)
        ->user_id->toBe($user->id)
        ->session_id->toBeNull();
});

test('the chat continues in the new context', function () {
    Queue::fake();
    $user = User::factory()->create();
    $project = Project::factory()->create(['path' => sys_get_temp_dir()]);
    $agent = Agent::factory()->create();
    $context = AgentContext::factory()->create([
        'project_id' => $project->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)->post(route('agent-contexts.store', $context));
    $this->actingAs($user)->post(route('projects.runs.store', $project), ['prompt' => 'Új téma.', 'agent_id' => $agent->id]);

    expect(PromptRun::sole()->agent_context_id)->not->toBe($context->id);
});

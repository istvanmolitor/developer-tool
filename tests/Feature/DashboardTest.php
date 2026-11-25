<?php

use App\Enums\PromptRunStatus;
use App\Models\Agent;
use App\Models\AgentContext;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows the running prompt and the queued prompts in order', function () {
    $this->actingAs(User::factory()->create());

    $running = PromptRun::factory()->create([
        'status' => PromptRunStatus::Running,
        'started_at' => now(),
    ]);
    $firstQueued = PromptRun::factory()->create();
    $secondQueued = PromptRun::factory()->create();
    PromptRun::factory()->completed()->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('promptQueue.running', 1)
            ->where('promptQueue.running.0.id', $running->id)
            ->where('promptQueue.running.0.project.name', $running->project->name)
            ->where('promptQueue.queued.0.id', $firstQueued->id)
            ->where('promptQueue.queued.1.id', $secondQueued->id)
            ->where('promptQueue.queued_count', 2)
        );
});

test('the dashboard lists only the first queued prompts but counts all of them', function () {
    $this->actingAs(User::factory()->create());

    PromptRun::factory()->count(12)->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('promptQueue.queued', 10)
            ->where('promptQueue.queued_count', 12)
        );
});

test('the dashboard chat defaults to the system project and shows the user\'s current conversation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $system = Project::factory()->create(['name' => 'zzz-system']);
    $system->forceFill(['is_system' => true])->save();
    Project::factory()->create(['name' => 'aaa-other']);

    $older = AgentContext::factory()->create(['project_id' => $system->id, 'user_id' => $user->id]);
    PromptRun::factory()->completed()->create(['agent_context_id' => $older->id]);
    $current = AgentContext::factory()->create(['project_id' => $system->id, 'user_id' => $user->id]);
    $first = PromptRun::factory()->completed()->create(['agent_context_id' => $current->id]);
    $second = PromptRun::factory()->create(['agent_context_id' => $current->id]);

    // Another user's conversation stays hidden.
    AgentContext::factory()->create(['project_id' => $system->id, 'user_id' => User::factory()->create()->id]);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('chat.project', 'zzz-system')
            ->where('chat.agent_id', null)
            ->where('chat.context.id', $current->id)
            ->has('chat.runs', 2)
            ->where('chat.runs.0.id', $first->id)
            ->where('chat.runs.1.id', $second->id)
        );
});

test('the dashboard chat shows the conversation of the chosen project and agent', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();
    $agent = Agent::factory()->create();
    AgentContext::factory()->create(['project_id' => $project->id, 'user_id' => $user->id]);
    $agentContext = AgentContext::factory()->create([
        'project_id' => $project->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
    ]);
    $run = PromptRun::factory()->create(['agent_context_id' => $agentContext->id]);

    $this->get(route('dashboard', ['project' => $project->name, 'agent' => $agent->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('chat.project', $project->name)
            ->where('chat.agent_id', $agent->id)
            ->where('chat.context.id', $agentContext->id)
            ->has('chat.runs', 1)
            ->where('chat.runs.0.id', $run->id)
        );
});

test('viewing the dashboard chat does not start a conversation', function () {
    $this->actingAs(User::factory()->create());
    Project::factory()->create();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('chat.context', null)
            ->has('chat.runs', 0)
        );

    expect(AgentContext::count())->toBe(0);
});

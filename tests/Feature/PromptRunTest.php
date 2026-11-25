<?php

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\Agent;
use App\Models\AgentContext;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath.'/alpha', 0755, true);

    config(['developer-tool.projects_path' => $this->projectsPath]);

    $this->alpha = Project::factory()->create(['name' => 'alpha', 'path' => $this->projectsPath.'/alpha']);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('submitting a prompt queues a claude run', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.runs.store', 'alpha'), ['prompt' => 'Add a README file.'])
        ->assertRedirect();

    $run = PromptRun::sole();
    expect($run->agentContext->project_id)->toBe($this->alpha->id)
        ->and($run->agentContext->user_id)->toBe($user->id)
        ->and($run->prompt)->toBe('Add a README file.')
        ->and($run->status)->toBe(PromptRunStatus::Pending);

    Queue::assertPushed(RunClaudePrompt::class, fn (RunClaudePrompt $job) => $job->promptRun->is($run));
});

test('submitting a prompt with an agent queues the run as that agent', function () {
    Queue::fake();

    $user = User::factory()->create();
    $agent = Agent::factory()->create();

    $this->actingAs($user)
        ->post(route('projects.runs.store', 'alpha'), [
            'prompt' => 'Refactor the service layer.',
            'agent_id' => $agent->id,
        ])
        ->assertRedirect();

    $run = PromptRun::sole();
    expect($run->agentContext->agent_id)->toBe($agent->id)
        ->and($run->prompt)->toBe('Refactor the service layer.')
        ->and($run->status)->toBe(PromptRunStatus::Pending);

    Queue::assertPushed(RunClaudePrompt::class, fn (RunClaudePrompt $job) => $job->promptRun->is($run));
});

test('the agent must exist', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.runs.store', 'alpha'), ['prompt' => 'Hello there.', 'agent_id' => 999999])
        ->assertSessionHasErrors('agent_id');

    Queue::assertNothingPushed();
});

test('a prompt is not required when an agent is selected', function () {
    Queue::fake();

    $agent = Agent::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.runs.store', 'alpha'), ['agent_id' => $agent->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(PromptRun::sole()->agentContext->agent_id)->toBe($agent->id);
});

test('the prompt is required', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.runs.store', 'alpha'), ['prompt' => ''])
        ->assertSessionHasErrors('prompt');

    Queue::assertNothingPushed();
});

test('a prompt cannot be submitted for an unknown project', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.runs.store', 'ghost'), ['prompt' => 'Hello'])
        ->assertNotFound();
});

test('a chat continues the latest context of the project, agent and user', function () {
    Queue::fake();
    $user = User::factory()->create();
    $agent = Agent::factory()->create();
    $context = AgentContext::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $agent->id,
        'user_id' => $user->id,
        'session_id' => 'session-abc',
    ]);

    $this->actingAs($user)
        ->post(route('projects.runs.store', 'alpha'), ['prompt' => 'Folytasd.', 'agent_id' => $agent->id])
        ->assertRedirect();

    expect(PromptRun::sole()->agent_context_id)->toBe($context->id);
});

test('a chat of another user or agent starts a context of its own', function () {
    Queue::fake();
    $user = User::factory()->create();
    $agent = Agent::factory()->create();
    $otherUsersContext = AgentContext::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $agent->id,
        'user_id' => User::factory()->create()->id,
    ]);

    $this->actingAs($user)
        ->post(route('projects.runs.store', 'alpha'), ['prompt' => 'Szia.', 'agent_id' => $agent->id])
        ->assertRedirect();

    expect(PromptRun::sole()->agentContext)
        ->id->not->toBe($otherUsersContext->id)
        ->user_id->toBe($user->id)
        ->agent_id->toBe($agent->id)
        ->project_id->toBe($this->alpha->id);
});

test('a chat can continue a chosen earlier context of the user', function () {
    Queue::fake();
    $user = User::factory()->create();
    $earlier = AgentContext::factory()->create(['project_id' => $this->alpha->id, 'user_id' => $user->id]);
    AgentContext::factory()->create(['project_id' => $this->alpha->id, 'user_id' => $user->id]);

    $this->actingAs($user)
        ->post(route('projects.runs.store', 'alpha'), ['prompt' => 'Folytasd.', 'agent_context_id' => $earlier->id])
        ->assertRedirect();

    expect(PromptRun::sole()->agent_context_id)->toBe($earlier->id);
});

test('a chat cannot continue another user\'s or another project\'s context', function () {
    Queue::fake();
    $user = User::factory()->create();
    $otherUsersContext = AgentContext::factory()->create([
        'project_id' => $this->alpha->id,
        'user_id' => User::factory()->create()->id,
    ]);
    $otherProjectsContext = AgentContext::factory()->create(['user_id' => $user->id]);

    foreach ([$otherUsersContext, $otherProjectsContext] as $context) {
        $this->actingAs($user)
            ->post(route('projects.runs.store', 'alpha'), ['prompt' => 'Szia.', 'agent_context_id' => $context->id])
            ->assertNotFound();
    }

    expect(PromptRun::count())->toBe(0);
});

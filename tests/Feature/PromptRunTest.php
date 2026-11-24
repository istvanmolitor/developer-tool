<?php

use App\Enums\PromptRunStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\Agent;
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
    expect($run->project_id)->toBe($this->alpha->id)
        ->and($run->user_id)->toBe($user->id)
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
    expect($run->agent_id)->toBe($agent->id)
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

    expect(PromptRun::sole()->agent_id)->toBe($agent->id);
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

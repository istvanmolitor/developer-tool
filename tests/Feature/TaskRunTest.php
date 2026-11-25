<?php

use App\Enums\PromptRunStatus;
use App\Enums\TaskStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
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

test('running a task queues a run as its assigned agent with the task as prompt', function () {
    Queue::fake();

    $agent = Agent::factory()->create(['name' => 'backend-developer']);
    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $agent->id,
        'status' => TaskStatus::Todo,
        'title' => 'Készíts REST végpontot',
        'description' => 'A projektek listázásához.',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('projects.tasks.run', ['project' => 'alpha', 'task' => $task->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($task->refresh()->status)->toBe(TaskStatus::InProgress);

    $run = PromptRun::sole();
    expect($run->agentContext->agent_id)->toBe($agent->id)
        ->and($run->agentContext->project_id)->toBe($this->alpha->id)
        ->and($run->status)->toBe(PromptRunStatus::Pending)
        ->and($run->prompt)->toContain("#{$task->id}")
        ->and($run->prompt)->toContain('Készíts REST végpontot')
        ->and($run->prompt)->toContain('A projektek listázásához.');

    Queue::assertPushed(RunClaudePrompt::class, fn (RunClaudePrompt $job) => $job->promptRun->is($run));
});

test('running a task without an assigned agent queues nothing', function () {
    Queue::fake();

    $task = Task::factory()->create(['project_id' => $this->alpha->id, 'agent_id' => null]);

    $this->actingAs(User::factory()->create())
        ->post(route('projects.tasks.run', ['project' => 'alpha', 'task' => $task->id]))
        ->assertRedirect();

    expect(PromptRun::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a task of another project cannot be run through this project', function () {
    Queue::fake();

    $other = Project::factory()->create(['name' => 'beta']);
    $task = Task::factory()->create(['project_id' => $other->id, 'agent_id' => Agent::factory()->create()->id]);

    $this->actingAs(User::factory()->create())
        ->post(route('projects.tasks.run', ['project' => 'alpha', 'task' => $task->id]))
        ->assertNotFound();

    Queue::assertNothingPushed();
});

test('running a task requires authentication', function () {
    $task = Task::factory()->create(['project_id' => $this->alpha->id, 'agent_id' => Agent::factory()->create()->id]);

    $this->post(route('projects.tasks.run', ['project' => 'alpha', 'task' => $task->id]))
        ->assertRedirect(route('login'));
});

<?php

use App\Enums\PromptRunStatus;
use App\Enums\TaskStatus;
use App\Jobs\RunClaudePrompt;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath.'/alpha', 0755, true);

    config(['developer-tool.projects_path' => $this->projectsPath]);

    $this->alpha = Project::factory()->create(['name' => 'alpha', 'path' => $this->projectsPath.'/alpha']);
    $this->agent = Agent::factory()->create(['name' => 'backend-developer']);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('creating an auto-run runnable task with an agent starts it and moves it to in progress', function () {
    Queue::fake();

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $this->agent->id,
        'auto_run' => true,
        'status' => TaskStatus::Todo,
        'title' => 'Készíts REST végpontot',
        'description' => 'A projektek listázásához.',
    ]);

    expect($task->refresh()->status)->toBe(TaskStatus::InProgress);

    $run = PromptRun::sole();
    expect($run->agentContext->agent_id)->toBe($this->agent->id)
        ->and($run->agentContext->project_id)->toBe($this->alpha->id)
        ->and($run->status)->toBe(PromptRunStatus::Pending)
        ->and($run->prompt)->toContain("#{$task->id}")
        ->and($run->prompt)->toContain('get-task');

    Queue::assertPushed(RunClaudePrompt::class, fn (RunClaudePrompt $job) => $job->promptRun->is($run));
});

test('creating a task that does not qualify for auto-run starts nothing', function () {
    Queue::fake();

    // auto_run off
    Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $this->agent->id,
        'auto_run' => false,
        'status' => TaskStatus::Todo,
    ]);

    // no agent
    Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => null,
        'auto_run' => true,
        'status' => TaskStatus::Todo,
    ]);

    // not a runnable status (draft is still being prepared, closed is terminal)
    Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $this->agent->id,
        'auto_run' => true,
        'status' => TaskStatus::Draft,
    ]);

    Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $this->agent->id,
        'auto_run' => true,
        'status' => TaskStatus::Closed,
    ]);

    expect(PromptRun::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('assigning an agent to an auto-run task starts it', function () {
    Queue::fake();

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => null,
        'auto_run' => true,
        'status' => TaskStatus::Todo,
    ]);

    expect(PromptRun::count())->toBe(0);

    $task->update(['agent_id' => $this->agent->id]);

    expect($task->refresh()->status)->toBe(TaskStatus::InProgress)
        ->and(PromptRun::count())->toBe(1);

    Queue::assertPushed(RunClaudePrompt::class);
});

test('promoting a draft auto-run task to todo starts it', function () {
    Queue::fake();

    // Draft is not runnable, so creating it starts nothing.
    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $this->agent->id,
        'auto_run' => true,
        'status' => TaskStatus::Draft,
    ]);

    expect(PromptRun::count())->toBe(0);

    // Entering the actionable "todo" state signals the agent to pick it up.
    $task->update(['status' => TaskStatus::Todo]);

    expect($task->refresh()->status)->toBe(TaskStatus::InProgress)
        ->and(PromptRun::count())->toBe(1);
    Queue::assertPushed(RunClaudePrompt::class);
});

test('finishing or re-running for the same agent does not start it again', function () {
    Queue::fake();

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $this->agent->id,
        'auto_run' => true,
        'status' => TaskStatus::Todo,
    ]);

    // Creation already queued exactly one run and moved it to in_progress.
    expect(PromptRun::count())->toBe(1);

    // The agent finishing its work (done) is not new work for the same agent.
    $task->update(['status' => TaskStatus::Done]);

    // Re-saving with the same agent and no actionable change starts nothing more.
    $task->update(['priority' => \App\Enums\TaskPriority::High]);

    expect(PromptRun::count())->toBe(1);
});

test('reassigning to a different agent starts it again', function () {
    Queue::fake();

    $other = Agent::factory()->create(['name' => 'frontend-developer']);

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $this->agent->id,
        'auto_run' => true,
        'status' => TaskStatus::Todo,
    ]);

    expect(PromptRun::count())->toBe(1);

    $task->update(['agent_id' => $other->id]);

    expect(PromptRun::count())->toBe(2);
});

test('runnable statuses are todo, in progress and done', function () {
    expect(TaskStatus::Todo->isRunnable())->toBeTrue()
        ->and(TaskStatus::InProgress->isRunnable())->toBeTrue()
        ->and(TaskStatus::Done->isRunnable())->toBeTrue()
        ->and(TaskStatus::Draft->isRunnable())->toBeFalse()
        ->and(TaskStatus::Closed->isRunnable())->toBeFalse();
});

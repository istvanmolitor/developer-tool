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

test('it starts auto-run todo tasks that have an agent and moves them to in progress', function () {
    Queue::fake();

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $this->agent->id,
        'auto_run' => true,
        'status' => TaskStatus::Todo,
        'title' => 'Készíts REST végpontot',
        'description' => 'A projektek listázásához.',
    ]);

    $this->artisan('tasks:run-auto')->assertSuccessful();

    expect($task->refresh()->status)->toBe(TaskStatus::InProgress);

    $run = PromptRun::sole();
    expect($run->agentContext->agent_id)->toBe($this->agent->id)
        ->and($run->agentContext->project_id)->toBe($this->alpha->id)
        ->and($run->status)->toBe(PromptRunStatus::Pending)
        ->and($run->prompt)->toContain("#{$task->id}")
        ->and($run->prompt)->toContain('get-task');

    Queue::assertPushed(RunClaudePrompt::class, fn (RunClaudePrompt $job) => $job->promptRun->is($run));
});

test('it ignores tasks that are not eligible', function () {
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

    // not todo
    Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $this->agent->id,
        'auto_run' => true,
        'status' => TaskStatus::InProgress,
    ]);

    $this->artisan('tasks:run-auto')->assertSuccessful();

    expect(PromptRun::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('an already started auto-run task is not picked up again', function () {
    Queue::fake();

    $task = Task::factory()->create([
        'project_id' => $this->alpha->id,
        'agent_id' => $this->agent->id,
        'auto_run' => true,
        'status' => TaskStatus::Todo,
    ]);

    $this->artisan('tasks:run-auto')->assertSuccessful();
    $this->artisan('tasks:run-auto')->assertSuccessful();

    expect(PromptRun::count())->toBe(1)
        ->and($task->refresh()->status)->toBe(TaskStatus::InProgress);
});

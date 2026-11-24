<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Mcp\Servers\TaskServer;
use App\Mcp\Tools\UpdateTaskTool;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
use App\Models\TaskChange;
use App\Models\User;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'alpha']);
    $this->task = Task::factory()->create([
        'project_id' => $this->project->id,
        'status' => TaskStatus::Todo,
        'priority' => TaskPriority::Low,
        'type' => TaskType::Development,
        'due_date' => null,
    ]);
});

afterEach(function () {
    putenv(TaskServer::PROMPT_RUN_ENV);
});

test('a change made in the web ui is logged with the old values and the user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('projects.tasks.update', ['alpha', $this->task]), [
        'title' => $this->task->title,
        'status' => 'in_progress',
        'priority' => 'high',
        'type' => 'development',
        'due_date' => '2026-12-01',
    ])->assertRedirect();

    $entry = TaskChange::sole();
    expect($entry->user_id)->toBe($user->id)
        ->and($entry->agent_id)->toBeNull()
        ->and($entry->changes)->toEqual([
            'status' => ['old' => 'todo', 'new' => 'in_progress'],
            'priority' => ['old' => 'low', 'new' => 'high'],
            'due_date' => ['old' => null, 'new' => '2026-12-01'],
        ]);
});

test('title and description changes are not logged', function () {
    $this->task->update(['title' => 'Új cím', 'description' => 'Új leírás']);

    expect(TaskChange::count())->toBe(0);
});

test('a change made through the mcp by an agent run is logged with the agent', function () {
    $agent = Agent::factory()->create(['name' => 'backend']);
    $run = PromptRun::factory()->create(['agent_id' => $agent->id]);
    putenv(TaskServer::PROMPT_RUN_ENV.'='.$run->id);

    TaskServer::tool(UpdateTaskTool::class, [
        'task_id' => $this->task->id,
        'status' => 'done',
        'agent' => 'backend',
    ])->assertOk();

    $entry = TaskChange::sole();
    expect($entry->agent_id)->toBe($agent->id)
        ->and($entry->user_id)->toBeNull()
        ->and($entry->changes)->toEqual([
            'status' => ['old' => 'todo', 'new' => 'done'],
            'agent_id' => ['old' => null, 'new' => $agent->id],
        ]);
});

test('an mcp change outside an agent run is logged without an actor', function () {
    TaskServer::tool(UpdateTaskTool::class, [
        'task_id' => $this->task->id,
        'status' => 'done',
    ])->assertOk();

    $entry = TaskChange::sole();
    expect($entry->agent_id)->toBeNull()
        ->and($entry->user_id)->toBeNull();
});

test('the project change log lists the changes with labels', function () {
    $agent = Agent::factory()->create(['name' => 'backend']);
    TaskChange::factory()->create([
        'task_id' => $this->task->id,
        'agent_id' => $agent->id,
        'changes' => ['status' => ['old' => 'todo', 'new' => 'done']],
    ]);
    TaskChange::factory()->create(); // another project's task

    $this->actingAs(User::factory()->create())
        ->get(route('projects.task-changes.index', 'alpha'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/TaskChanges/Index')
            ->has('entries', 1)
            ->where('entries.0.task.id', $this->task->id)
            ->where('entries.0.actor', ['type' => 'agent', 'name' => 'backend'])
            ->where('entries.0.changes.0', [
                'field' => 'status',
                'label' => 'Státusz',
                'old' => 'Teendő',
                'new' => 'Kész',
            ]),
        );
});

test('the task page shows the task history', function () {
    $user = User::factory()->create(['name' => 'Kati']);
    TaskChange::factory()->create([
        'task_id' => $this->task->id,
        'user_id' => $user->id,
        'changes' => ['priority' => ['old' => 'low', 'new' => 'high']],
    ]);

    $this->actingAs($user)
        ->get(route('projects.tasks.show', ['alpha', $this->task]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('history', 1)
            ->where('history.0.actor', ['type' => 'user', 'name' => 'Kati'])
            ->where('history.0.changes.0.old', 'Alacsony'),
        );
});

test('guests cannot see the change log', function () {
    $this->get(route('projects.task-changes.index', 'alpha'))
        ->assertRedirect(route('login'));
});

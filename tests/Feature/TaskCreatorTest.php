<?php

use App\Mcp\Servers\TaskServer;
use App\Mcp\Tools\CreateTaskTool;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'alpha']);
});

afterEach(function () {
    putenv(TaskServer::PROMPT_RUN_ENV);
});

test('a task created from the web UI records the logged-in user as its creator', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('projects.tasks.store', 'alpha'), [
        'title' => 'Webes feladat',
    ])->assertRedirect();

    $task = Task::sole();
    expect($task->created_by_user_id)->toBe($user->id)
        ->and($task->created_by_agent_id)->toBeNull()
        ->and($task->creator)->toBe(['type' => 'user', 'name' => $user->name]);
});

test('a task created through the mcp by an agent run records the agent as its creator', function () {
    $agent = Agent::factory()->create(['name' => 'backend']);
    $run = PromptRun::factory()->inContext(['agent_id' => $agent->id])->create();
    putenv(TaskServer::PROMPT_RUN_ENV.'='.$run->id);

    TaskServer::tool(CreateTaskTool::class, [
        'project' => 'alpha',
        'title' => 'MCP feladat',
    ])->assertOk();

    $task = Task::sole();
    expect($task->created_by_agent_id)->toBe($agent->id)
        ->and($task->created_by_user_id)->toBeNull()
        ->and($task->creator)->toBe(['type' => 'agent', 'name' => 'backend']);
});

test('a task created through the mcp outside an agent run has no creator', function () {
    TaskServer::tool(CreateTaskTool::class, [
        'project' => 'alpha',
        'title' => 'Gazdátlan feladat',
    ])->assertOk();

    $task = Task::sole();
    expect($task->created_by_user_id)->toBeNull()
        ->and($task->created_by_agent_id)->toBeNull()
        ->and($task->creator)->toBe(['type' => null, 'name' => null]);
});

test('the task detail page exposes the creator', function () {
    $user = User::factory()->create(['name' => 'Kovács Anna']);
    $task = Task::factory()->for($this->project)->create([
        'created_by_user_id' => $user->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('projects.tasks.show', ['alpha', $task]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('task.creator.type', 'user')
            ->where('task.creator.name', 'Kovács Anna')
        );
});

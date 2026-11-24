<?php

use App\Mcp\Servers\TaskServer;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\DeleteTaskTool;
use App\Mcp\Tools\ListAgentsTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'alpha']);
});

test('the server registers all task tools', function () {
    TaskServer::tool(ListProjectsTool::class);
    TaskServer::tool(ListTasksTool::class);
    TaskServer::tool(CreateTaskTool::class);
    TaskServer::tool(UpdateTaskTool::class);
    TaskServer::tool(DeleteTaskTool::class);
})->throwsNoExceptions();

test('list-projects returns projects with task counts', function () {
    Task::factory()->count(2)->create(['project_id' => $this->project->id]);

    TaskServer::tool(ListProjectsTool::class)
        ->assertOk()
        ->assertSee('alpha');
});

test('create-task creates a task under a project', function () {
    TaskServer::tool(CreateTaskTool::class, [
        'project' => 'alpha',
        'title' => 'MCP feladat',
        'priority' => 'high',
    ])->assertOk();

    $task = Task::sole();
    expect($task->title)->toBe('MCP feladat')
        ->and($task->priority->value)->toBe('high')
        ->and($task->project_id)->toBe($this->project->id);
});

test('list-agents returns the agents', function () {
    Agent::factory()->create(['name' => 'documentarian']);

    TaskServer::tool(ListAgentsTool::class)
        ->assertOk()
        ->assertSee('documentarian');
});

test('create-task assigns an agent by name', function () {
    $agent = Agent::factory()->create(['name' => 'documentarian']);

    TaskServer::tool(CreateTaskTool::class, [
        'project' => 'alpha',
        'title' => 'Dokumentálandó',
        'agent' => 'documentarian',
    ])->assertOk();

    expect(Task::sole()->agent_id)->toBe($agent->id);
});

test('create-task errors on an unknown agent', function () {
    TaskServer::tool(CreateTaskTool::class, [
        'project' => 'alpha',
        'title' => 'x',
        'agent' => 'nincs-ilyen-agent',
    ])->assertHasErrors();
});

test('update-task reassigns and can clear the agent', function () {
    $agent = Agent::factory()->create(['name' => 'documentarian']);
    $task = Task::factory()->create(['project_id' => $this->project->id]);

    TaskServer::tool(UpdateTaskTool::class, [
        'task_id' => $task->id,
        'agent' => 'documentarian',
    ])->assertOk();

    expect($task->fresh()->agent_id)->toBe($agent->id);

    TaskServer::tool(UpdateTaskTool::class, [
        'task_id' => $task->id,
        'agent' => '',
    ])->assertOk();

    expect($task->fresh()->agent_id)->toBeNull();
});

test('create-task errors on an unknown project', function () {
    TaskServer::tool(CreateTaskTool::class, [
        'project' => 'nincs-ilyen',
        'title' => 'x',
    ])->assertHasErrors();
});

test('list-tasks returns only the project tasks and filters by status', function () {
    Task::factory()->create(['project_id' => $this->project->id, 'status' => 'todo', 'title' => 'Teendő elem']);
    Task::factory()->create(['project_id' => $this->project->id, 'status' => 'done', 'title' => 'Kész elem']);

    TaskServer::tool(ListTasksTool::class, ['project' => 'alpha', 'status' => 'done'])
        ->assertOk()
        ->assertSee('Kész elem')
        ->assertDontSee('Teendő elem');
});

test('update-task changes the status', function () {
    $task = Task::factory()->create(['project_id' => $this->project->id, 'status' => 'todo']);

    TaskServer::tool(UpdateTaskTool::class, [
        'task_id' => $task->id,
        'status' => 'done',
    ])->assertOk();

    expect($task->fresh()->status->value)->toBe('done');
});

test('delete-task removes the task', function () {
    $task = Task::factory()->create(['project_id' => $this->project->id]);

    TaskServer::tool(DeleteTaskTool::class, ['task_id' => $task->id])
        ->assertOk();

    expect(Task::find($task->id))->toBeNull();
});

test('delete-task errors on a missing task', function () {
    TaskServer::tool(DeleteTaskTool::class, ['task_id' => 999999])
        ->assertHasErrors();
});

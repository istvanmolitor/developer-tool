<?php

use App\Mcp\Servers\TaskServer;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\DeleteTaskTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\NextTaskTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Models\Agent;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
use App\Models\TaskChange;
use App\Models\TaskComment;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'alpha']);
});

test('the server registers all task tools', function () {
    TaskServer::tool(ListTasksTool::class);
    TaskServer::tool(CreateTaskTool::class);
    TaskServer::tool(UpdateTaskTool::class);
    TaskServer::tool(DeleteTaskTool::class);
})->throwsNoExceptions();

test('create-task creates a task under a project', function () {
    TaskServer::tool(CreateTaskTool::class, [
        'project' => 'alpha',
        'title' => 'MCP feladat',
        'priority' => 'high',
        'type' => 'design',
    ])->assertOk();

    $task = Task::sole();
    expect($task->title)->toBe('MCP feladat')
        ->and($task->priority->value)->toBe('high')
        ->and($task->type->value)->toBe('design')
        ->and($task->project_id)->toBe($this->project->id);
});

test('create-task links the given attachments to the new task', function () {
    $one = Attachment::factory()->for($this->project)->create(['filename' => 'terv.txt']);
    $two = Attachment::factory()->for($this->project)->create(['filename' => 'kep.png']);

    TaskServer::tool(CreateTaskTool::class, [
        'project' => 'alpha',
        'title' => 'Feladat melléklettel',
        'attachment_ids' => [$one->id, $two->id],
    ])
        ->assertOk()
        ->assertSee('2 csatolmánnyal')
        ->assertSee('terv.txt')
        ->assertSee('kep.png');

    $task = Task::sole();
    expect($task->attachments()->pluck('attachments.id')->all())
        ->toEqualCanonicalizing([$one->id, $two->id]);
});

test('create-task errors on an unknown attachment id', function () {
    TaskServer::tool(CreateTaskTool::class, [
        'project' => 'alpha',
        'title' => 'Feladat',
        'attachment_ids' => [999999],
    ])->assertHasErrors();

    // The task is not created when an attachment is invalid.
    expect(Task::count())->toBe(0);
});

test('create-task rejects an attachment belonging to another project', function () {
    $foreign = Attachment::factory()->create(); // another project

    TaskServer::tool(CreateTaskTool::class, [
        'project' => 'alpha',
        'title' => 'Feladat',
        'attachment_ids' => [$foreign->id],
    ])->assertHasErrors();

    expect(Task::count())->toBe(0);
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

test('update-task can close a task', function () {
    $task = Task::factory()->create(['project_id' => $this->project->id, 'status' => 'done']);

    TaskServer::tool(UpdateTaskTool::class, [
        'task_id' => $task->id,
        'status' => 'closed',
    ])->assertOk();

    expect($task->fresh()->status->value)->toBe('closed');
});

test('get-task returns the full task context and flags it as the current agents', function () {
    $agent = Agent::factory()->create(['name' => 'backend-developer']);
    $run = PromptRun::factory()
        ->inContext(['project_id' => $this->project->id, 'agent_id' => $agent->id])
        ->create();

    $task = Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'title' => 'MCP olvasós feladat',
        'description' => 'A teljes kontextus.',
        'status' => 'in_progress',
        'priority' => 'high',
    ]);

    TaskComment::factory()->create([
        'task_id' => $task->id,
        'agent_id' => $agent->id,
        'body' => 'Elkezdtem a munkát.',
    ]);
    TaskChange::factory()->create([
        'task_id' => $task->id,
        'agent_id' => $agent->id,
        'changes' => ['status' => ['old' => 'todo', 'new' => 'in_progress']],
    ]);

    $attachment = Attachment::factory()->create(['project_id' => $this->project->id]);
    $task->attachments()->attach($attachment->id);

    // Bind the call to the agent's run, so get-task can tell the task is "mine".
    putenv(TaskServer::PROMPT_RUN_ENV.'='.$run->id);
    $response = TaskServer::tool(GetTaskTool::class, ['task_id' => $task->id]);
    putenv(TaskServer::PROMPT_RUN_ENV);

    $response->assertOk()
        ->assertSee('MCP olvasós feladat')
        ->assertSee('A teljes kontextus.')
        ->assertSee('Folyamatban')          // status_label
        ->assertSee('Elkezdtem a munkát.')  // comment history
        ->assertSee('backend-developer')    // assigned agent / comment author
        ->assertSee('"assigned_to_you":true')
        ->assertSee($attachment->filename);
});

test('get-task errors on a missing task', function () {
    TaskServer::tool(GetTaskTool::class, ['task_id' => 999999])->assertHasErrors();
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

test('next-task returns the agents highest-priority open task', function () {
    $agent = Agent::factory()->create(['name' => 'backend-developer']);

    Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'title' => 'Alacsony prioritású',
        'status' => 'todo',
        'priority' => 'low',
    ]);
    Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'title' => 'Magas prioritású',
        'status' => 'todo',
        'priority' => 'high',
    ]);

    TaskServer::tool(NextTaskTool::class, ['agent' => 'backend-developer'])
        ->assertOk()
        ->assertSee('Magas prioritású')
        ->assertSee('"remaining":2');
});

test('next-task prefers an in-progress task over a todo one', function () {
    $agent = Agent::factory()->create(['name' => 'backend-developer']);

    Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'title' => 'Teendő magas',
        'status' => 'todo',
        'priority' => 'high',
    ]);
    Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'title' => 'Már elkezdett',
        'status' => 'in_progress',
        'priority' => 'low',
    ]);

    TaskServer::tool(NextTaskTool::class, ['agent' => 'backend-developer'])
        ->assertOk()
        ->assertSee('Már elkezdett');
});

test('next-task ignores done, closed and other agents tasks', function () {
    $agent = Agent::factory()->create(['name' => 'backend-developer']);
    $other = Agent::factory()->create(['name' => 'frontend-developer']);

    Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'status' => 'done',
        'title' => 'Kész feladat',
    ]);
    Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'status' => 'closed',
        'title' => 'Lezárt feladat',
    ]);
    Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $other->id,
        'status' => 'todo',
        'title' => 'Másik agent feladata',
    ]);

    TaskServer::tool(NextTaskTool::class, ['agent' => 'backend-developer'])
        ->assertOk()
        ->assertSee('"next_task":null')
        ->assertDontSee('Kész feladat')
        ->assertDontSee('Lezárt feladat')
        ->assertDontSee('Másik agent feladata');
});

test('next-task can scope to a single project', function () {
    $agent = Agent::factory()->create(['name' => 'backend-developer']);
    $beta = Project::factory()->create(['name' => 'beta']);

    Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
        'status' => 'todo',
        'title' => 'Alpha feladat',
    ]);
    Task::factory()->create([
        'project_id' => $beta->id,
        'agent_id' => $agent->id,
        'status' => 'todo',
        'title' => 'Beta feladat',
    ]);

    TaskServer::tool(NextTaskTool::class, ['agent' => 'backend-developer', 'project' => 'beta'])
        ->assertOk()
        ->assertSee('Beta feladat')
        ->assertDontSee('Alpha feladat');
});

test('next-task errors without an agent outside an agent run', function () {
    TaskServer::tool(NextTaskTool::class, [])->assertHasErrors();
});

test('next-task errors on an unknown agent', function () {
    TaskServer::tool(NextTaskTool::class, ['agent' => 'nincs-ilyen'])->assertHasErrors();
});

test('list-tasks can filter by type', function () {
    Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Bug feladat', 'type' => 'bug']);
    Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Design feladat', 'type' => 'design']);

    TaskServer::tool(ListTasksTool::class, ['project' => 'alpha', 'type' => 'bug'])
        ->assertOk()
        ->assertSee('Bug feladat')
        ->assertDontSee('Design feladat');
});

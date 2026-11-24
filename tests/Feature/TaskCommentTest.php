<?php

use App\Mcp\Servers\TaskServer;
use App\Mcp\Tools\AddTaskCommentTool;
use App\Mcp\Tools\ListTaskCommentsTool;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'alpha']);
    $this->task = Task::factory()->create(['project_id' => $this->project->id]);
});

afterEach(function () {
    putenv(TaskServer::PROMPT_RUN_ENV);
});

test('guests cannot comment on a task', function () {
    $this->post(route('projects.tasks.comments.store', ['alpha', $this->task]), ['body' => 'Szia'])
        ->assertRedirect(route('login'));

    expect(TaskComment::count())->toBe(0);
});

test('a user can comment on a task', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('projects.tasks.show', ['alpha', $this->task]))
        ->post(route('projects.tasks.comments.store', ['alpha', $this->task]), ['body' => 'Megnéztem, rendben.'])
        ->assertRedirect(route('projects.tasks.show', ['alpha', $this->task]))
        ->assertSessionHasNoErrors();

    $comment = TaskComment::sole();
    expect($comment->task_id)->toBe($this->task->id)
        ->and($comment->user_id)->toBe($user->id)
        ->and($comment->agent_id)->toBeNull()
        ->and($comment->body)->toBe('Megnéztem, rendben.');
});

test('a comment needs a body', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.tasks.comments.store', ['alpha', $this->task]), ['body' => ''])
        ->assertSessionHasErrors('body');

    expect(TaskComment::count())->toBe(0);
});

test('a task cannot be commented through another project', function () {
    Project::factory()->create(['name' => 'beta']);

    $this->actingAs(User::factory()->create())
        ->post(route('projects.tasks.comments.store', ['beta', $this->task]), ['body' => 'Szia'])
        ->assertNotFound();

    expect(TaskComment::count())->toBe(0);
});

test('the task page shows the comments oldest first with their authors', function () {
    $user = User::factory()->create(['name' => 'Anna']);
    $agent = Agent::factory()->create(['name' => 'backend']);
    TaskComment::factory()->for($this->task)->create(['user_id' => $user->id, 'body' => 'Első']);
    TaskComment::factory()->for($this->task)->create(['agent_id' => $agent->id, 'body' => 'Második']);

    $this->actingAs($user)
        ->get(route('projects.tasks.show', ['alpha', $this->task]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('comments', 2)
            ->where('comments.0.body', 'Első')
            ->where('comments.0.author', ['type' => 'user', 'name' => 'Anna'])
            ->where('comments.1.body', 'Második')
            ->where('comments.1.author', ['type' => 'agent', 'name' => 'backend']));
});

test('an agent can comment on a task through the mcp', function () {
    $agent = Agent::factory()->create(['name' => 'backend']);
    $run = PromptRun::factory()->create(['agent_id' => $agent->id]);
    putenv(TaskServer::PROMPT_RUN_ENV.'='.$run->id);

    TaskServer::tool(AddTaskCommentTool::class, [
        'task_id' => $this->task->id,
        'body' => 'Kész a migráció.',
    ])->assertOk()->assertSee('Kész a migráció.');

    $comment = TaskComment::sole();
    expect($comment->agent_id)->toBe($agent->id)
        ->and($comment->user_id)->toBeNull()
        ->and($comment->body)->toBe('Kész a migráció.');
});

test('commenting on a missing task through the mcp fails', function () {
    TaskServer::tool(AddTaskCommentTool::class, ['task_id' => 999999, 'body' => 'Szia'])
        ->assertHasErrors(['Nincs 999999 azonosítójú feladat.']);

    expect(TaskComment::count())->toBe(0);
});

test('the mcp lists a task\'s comments with their authors', function () {
    $agent = Agent::factory()->create(['name' => 'backend']);
    TaskComment::factory()->for($this->task)->create(['agent_id' => $agent->id, 'body' => 'Agent megjegyzés']);
    TaskComment::factory()->create(['body' => 'Másik feladaté']);

    TaskServer::tool(ListTaskCommentsTool::class, ['task_id' => $this->task->id])
        ->assertOk()
        ->assertSee('Agent megjegyzés')
        ->assertSee('backend')
        ->assertDontSee('Másik feladaté');
});

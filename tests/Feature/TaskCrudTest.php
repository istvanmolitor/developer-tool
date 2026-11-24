<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->project = Project::factory()->create(['name' => 'alpha']);
    $this->actingAs(User::factory()->create());
});

test('guests cannot reach the task index', function () {
    auth()->logout();

    $this->get(route('projects.tasks.index', 'alpha'))
        ->assertRedirect(route('login'));
});

test('the index lists the project tasks', function () {
    Task::factory()->create(['project_id' => $this->project->id, 'title' => 'Első feladat']);

    $this->get(route('projects.tasks.index', 'alpha'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Projects/Tasks/Index')
            ->has('tasks', 1)
            ->where('tasks.0.title', 'Első feladat'),
        );
});

test('a task can be created with defaults', function () {
    $this->post(route('projects.tasks.store', 'alpha'), [
        'title' => 'Új feladat',
    ])->assertRedirect(route('projects.tasks.index', 'alpha'));

    $task = Task::sole();
    expect($task->project_id)->toBe($this->project->id)
        ->and($task->title)->toBe('Új feladat')
        ->and($task->status)->toBe(TaskStatus::Todo)
        ->and($task->priority)->toBe(TaskPriority::Medium)
        ->and($task->type)->toBe(TaskType::Development);
});

test('a task can be created with a type', function () {
    $this->post(route('projects.tasks.store', 'alpha'), [
        'title' => 'Hibás gomb',
        'type' => TaskType::Bug->value,
    ])->assertRedirect(route('projects.tasks.index', 'alpha'));

    expect(Task::sole()->type)->toBe(TaskType::Bug);
});

test('an invalid type is rejected', function () {
    $this->post(route('projects.tasks.store', 'alpha'), [
        'title' => 'X',
        'type' => 'nope',
    ])->assertSessionHasErrors('type');
});

test('a task can be assigned to an agent', function () {
    $agent = Agent::factory()->create();

    $this->post(route('projects.tasks.store', 'alpha'), [
        'title' => 'Hozzárendelt feladat',
        'agent_id' => $agent->id,
    ])->assertRedirect(route('projects.tasks.index', 'alpha'));

    expect(Task::sole()->agent_id)->toBe($agent->id);
});

test('the assigned agent must exist', function () {
    $this->post(route('projects.tasks.store', 'alpha'), [
        'title' => 'X',
        'agent_id' => 999999,
    ])->assertSessionHasErrors('agent_id');
});

test('an agent assignment can be cleared on update', function () {
    $agent = Agent::factory()->create();
    $task = Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
    ]);

    $this->patch(route('projects.tasks.update', ['project' => 'alpha', 'task' => $task->id]), [
        'agent_id' => null,
    ])->assertRedirect();

    expect($task->fresh()->agent_id)->toBeNull();
});

test('deleting an agent leaves the task but clears the assignment', function () {
    $agent = Agent::factory()->create();
    $task = Task::factory()->create([
        'project_id' => $this->project->id,
        'agent_id' => $agent->id,
    ]);

    $agent->delete();

    expect($task->fresh()->agent_id)->toBeNull();
});

test('the title is required', function () {
    $this->post(route('projects.tasks.store', 'alpha'), ['title' => ''])
        ->assertSessionHasErrors('title');
});

test('an invalid status is rejected', function () {
    $this->post(route('projects.tasks.store', 'alpha'), [
        'title' => 'X',
        'status' => 'nope',
    ])->assertSessionHasErrors('status');
});

test('a task can be updated', function () {
    $task = Task::factory()->create(['project_id' => $this->project->id]);

    $this->patch(route('projects.tasks.update', ['project' => 'alpha', 'task' => $task->id]), [
        'title' => 'Átírt cím',
        'status' => TaskStatus::Done->value,
    ])->assertRedirect(route('projects.tasks.index', 'alpha'));

    expect($task->fresh()->title)->toBe('Átírt cím')
        ->and($task->fresh()->status)->toBe(TaskStatus::Done);
});

test('a task can be deleted', function () {
    $task = Task::factory()->create(['project_id' => $this->project->id]);

    $this->delete(route('projects.tasks.destroy', ['project' => 'alpha', 'task' => $task->id]))
        ->assertRedirect(route('projects.tasks.index', 'alpha'));

    expect(Task::count())->toBe(0);
});

test('tasks are scoped to their project', function () {
    $other = Project::factory()->create(['name' => 'beta']);
    $task = Task::factory()->create(['project_id' => $other->id]);

    $this->get(route('projects.tasks.show', ['project' => 'alpha', 'task' => $task->id]))
        ->assertNotFound();
});

test('deleting a project cascades to its tasks', function () {
    Task::factory()->count(3)->create(['project_id' => $this->project->id]);

    $this->project->delete();

    expect(Task::count())->toBe(0);
});

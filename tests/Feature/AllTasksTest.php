<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('guests cannot reach the global task index', function () {
    auth()->logout();

    $this->get(route('tasks.index'))->assertRedirect(route('login'));
});

test('the index lists tasks from every project', function () {
    $alpha = Project::factory()->create(['name' => 'alpha']);
    $beta = Project::factory()->create(['name' => 'beta']);
    Task::factory()->create(['project_id' => $alpha->id, 'title' => 'Alpha feladat']);
    Task::factory()->create(['project_id' => $beta->id, 'title' => 'Beta feladat']);

    $this->get(route('tasks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Tasks/Index')
            ->has('tasks.data', 2)
            ->has('projects', 2)
            ->has('sorts')
            ->where('filters.sort', 'newest'),
        );
});

test('tasks can be filtered by project', function () {
    $alpha = Project::factory()->create(['name' => 'alpha']);
    $beta = Project::factory()->create(['name' => 'beta']);
    Task::factory()->create(['project_id' => $alpha->id, 'title' => 'Alpha feladat']);
    Task::factory()->create(['project_id' => $beta->id, 'title' => 'Beta feladat']);

    $this->get(route('tasks.index', ['project' => $beta->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.title', 'Beta feladat')
            ->where('tasks.data.0.project.name', 'beta'),
        );
});

test('tasks can be filtered by status', function () {
    $project = Project::factory()->create(['name' => 'alpha']);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Done]);

    $this->get(route('tasks.index', ['status' => TaskStatus::Done->value]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tasks.data', 1)
            ->where('tasks.data.0.status', TaskStatus::Done->value),
        );
});

test('tasks are sorted by title when requested', function () {
    $project = Project::factory()->create(['name' => 'alpha']);
    Task::factory()->create(['project_id' => $project->id, 'title' => 'Béta']);
    Task::factory()->create(['project_id' => $project->id, 'title' => 'Alfa']);

    $this->get(route('tasks.index', ['sort' => 'title']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tasks.data.0.title', 'Alfa')
            ->where('tasks.data.1.title', 'Béta'),
        );
});

test('an invalid sort key falls back to the default', function () {
    Project::factory()->create(['name' => 'alpha']);

    $this->get(route('tasks.index', ['sort' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.sort', 'newest'));
});

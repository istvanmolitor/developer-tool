<?php

use App\Models\PromptRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath.'/alpha', 0755, true);
    mkdir($this->projectsPath.'/beta', 0755, true);

    config(['developer-tool.projects_path' => $this->projectsPath]);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('guests are redirected from the projects index', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});

test('the projects index lists every project directory', function () {
    PromptRun::factory()->create(['project' => 'alpha']);

    $this->actingAs(User::factory()->create())
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Index')
            ->has('projects', 2)
            ->where('projects.0.runs_count', fn ($count) => is_int($count))
        );
});

test('a project can be viewed with its run history', function () {
    PromptRun::factory()->count(3)->create(['project' => 'alpha']);
    PromptRun::factory()->create(['project' => 'beta']);

    $this->actingAs(User::factory()->create())
        ->get(route('projects.show', 'alpha'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->where('project.name', 'alpha')
            ->has('runs', 3)
        );
});

test('viewing an unknown project returns 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('projects.show', 'does-not-exist'))
        ->assertNotFound();
});

test('a project name cannot escape the projects directory', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('projects.show', 'alpha/../beta'))
        ->assertNotFound();
});

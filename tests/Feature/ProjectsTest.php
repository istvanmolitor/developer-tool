<?php

use App\Enums\ProjectProvisionStatus;
use App\Jobs\ProvisionProject;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\User;
use Database\Seeders\ProjectSeeder;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath.'/alpha', 0755, true);
    mkdir($this->projectsPath.'/beta', 0755, true);

    config(['developer-tool.projects_path' => $this->projectsPath]);

    $this->alpha = Project::factory()->create(['name' => 'alpha', 'path' => $this->projectsPath.'/alpha']);
    $this->beta = Project::factory()->create(['name' => 'beta', 'path' => $this->projectsPath.'/beta']);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('guests are redirected from the projects index', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});

test('the projects index lists every registered project', function () {
    PromptRun::factory()->inContext(['project_id' => $this->alpha->id])->create();

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
    PromptRun::factory()->count(3)->inContext(['project_id' => $this->alpha->id])->create();
    PromptRun::factory()->inContext(['project_id' => $this->beta->id])->create();

    $this->actingAs(User::factory()->create())
        ->get(route('projects.show', $this->alpha))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Projects/Show')
            ->where('project.name', 'alpha')
            ->where('runs_count', 3)
        );
});

test('viewing an unknown project returns 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('projects.show', 'does-not-exist'))
        ->assertNotFound();
});

test('a project can be created from an existing folder', function () {
    Queue::fake();
    // An on-disk directory that is not yet registered as a project.
    mkdir($this->projectsPath.'/gamma', 0755, true);

    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [
            'mode' => 'existing',
            'directory' => 'gamma',
            'description' => 'A fresh project.',
        ])
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'name' => 'gamma',
        'path' => $this->projectsPath.'/gamma',
        'git_url' => null,
        'description' => 'A fresh project.',
        'provision_status' => ProjectProvisionStatus::Ready->value,
    ]);

    // An existing folder needs no provisioning.
    Queue::assertNothingPushed();
});

test('a new folder project creates its directory synchronously', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [
            'mode' => 'new',
            'name' => 'gamma',
        ])
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'name' => 'gamma',
        'path' => $this->projectsPath.'/gamma',
        'git_url' => null,
        'provision_status' => ProjectProvisionStatus::Ready->value,
    ]);

    // The empty folder exists the moment the request returns, no queue worker.
    expect(is_dir($this->projectsPath.'/gamma'))->toBeTrue();
    Queue::assertNotPushed(ProvisionProject::class);
});

test('a new folder project can be created with the empty fields the form sends', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [
            'mode' => 'new',
            'directory' => '',
            'name' => 'teszt2',
            'git_url' => '',
            'description' => '',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', ['name' => 'teszt2']);
});

test('a git project derives its folder name from the url and is queued', function () {
    Queue::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [
            'mode' => 'git',
            'git_url' => 'https://github.com/user/gamma.git',
        ])
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'name' => 'gamma',
        'path' => $this->projectsPath.'/gamma',
        'git_url' => 'https://github.com/user/gamma.git',
        'provision_status' => ProjectProvisionStatus::Pending->value,
    ]);

    Queue::assertPushed(ProvisionProject::class);
});

test('a new folder that already exists on disk is rejected', function () {
    mkdir($this->projectsPath.'/gamma', 0755, true);

    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [
            'mode' => 'new',
            'name' => 'gamma',
        ])
        ->assertSessionHasErrors('name');
});

test('an existing-folder mode requires a known directory', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [
            'mode' => 'existing',
            'directory' => 'not-on-disk',
        ])
        ->assertSessionHasErrors('directory');
});

test('a git project without a url falls back to creating an empty folder', function () {
    Queue::fake();

    // No git_url: the name cannot be derived, so it must be given explicitly.
    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [
            'mode' => 'git',
            'name' => 'gamma',
        ])
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'name' => 'gamma',
        'git_url' => null,
        'provision_status' => ProjectProvisionStatus::Ready->value,
    ]);

    // No clone to run, so the empty folder is created synchronously.
    expect(is_dir($this->projectsPath.'/gamma'))->toBeTrue();
    Queue::assertNotPushed(ProvisionProject::class);
});

test('a git project with neither url nor name is rejected', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [
            'mode' => 'git',
        ])
        ->assertSessionHasErrors('name');
});

test('a project name must be unique', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [
            'mode' => 'new',
            'name' => 'alpha',
        ])
        ->assertSessionHasErrors('name');
});

test('a project name cannot contain a slash', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('projects.store'), [
            'mode' => 'new',
            'name' => 'nested/name',
        ])
        ->assertSessionHasErrors('name');
});

test('a project can be updated', function () {
    $this->actingAs(User::factory()->create())
        ->put(route('projects.update', $this->alpha), [
            'name' => 'alpha',
            'path' => $this->projectsPath.'/alpha',
            'description' => 'Updated description.',
        ])
        ->assertRedirect(route('projects.show', $this->alpha));

    expect($this->alpha->fresh()->description)->toBe('Updated description.');
});

test('renaming a project keeps its run history attached', function () {
    PromptRun::factory()->count(2)->inContext(['project_id' => $this->alpha->id])->create();

    $this->actingAs(User::factory()->create())
        ->put(route('projects.update', $this->alpha), [
            'name' => 'alpha-renamed',
            'path' => $this->alpha->path,
        ])
        ->assertRedirect();

    // Runs are linked by project_id, so a rename leaves them attached.
    expect($this->alpha->fresh()->promptRuns()->count())->toBe(2);
});

test('a project can be deleted without touching the files on disk', function () {
    $this->actingAs(User::factory()->create())
        ->delete(route('projects.destroy', $this->alpha))
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseMissing('projects', ['id' => $this->alpha->id]);
    expect(is_dir($this->projectsPath.'/alpha'))->toBeTrue();
});

test('the seeder creates a single system project pointing at the app itself', function () {
    (new ProjectSeeder)->run();

    $system = Project::where('name', Project::SYSTEM_NAME)->firstOrFail();

    expect($system->is_system)->toBeTrue()
        ->and($system->path)->toBe(base_path());

    // Running it again is idempotent — still exactly one system project.
    (new ProjectSeeder)->run();

    expect(Project::where('is_system', true)->count())->toBe(1);
});

test('the system project cannot be deleted', function () {
    (new ProjectSeeder)->run();
    $system = Project::where('name', Project::SYSTEM_NAME)->firstOrFail();

    $this->actingAs(User::factory()->create())
        ->delete(route('projects.destroy', $system))
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', ['id' => $system->id]);
});

test('the system project path cannot be changed through the edit form', function () {
    (new ProjectSeeder)->run();
    $system = Project::where('name', Project::SYSTEM_NAME)->firstOrFail();

    $this->actingAs(User::factory()->create())
        ->put(route('projects.update', $system), [
            'name' => $system->name,
            'path' => '/somewhere/else',
            'description' => 'Updated description.',
        ])
        ->assertRedirect(route('projects.show', $system));

    $fresh = $system->fresh();

    // Path stays pinned to the application, but other fields still update.
    expect($fresh->path)->toBe(base_path())
        ->and($fresh->description)->toBe('Updated description.');
});

test('the system project path cannot be changed at the model level', function () {
    (new ProjectSeeder)->run();
    $system = Project::where('name', Project::SYSTEM_NAME)->firstOrFail();

    expect(fn () => $system->update(['path' => '/somewhere/else']))
        ->toThrow(RuntimeException::class);
    expect($system->fresh()->path)->toBe(base_path());
});

test('the system project cannot be deleted at the model level', function () {
    (new ProjectSeeder)->run();
    $system = Project::where('name', Project::SYSTEM_NAME)->firstOrFail();

    expect(fn () => $system->delete())->toThrow(RuntimeException::class);
    $this->assertDatabaseHas('projects', ['id' => $system->id]);
});

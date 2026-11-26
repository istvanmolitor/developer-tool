<?php

use App\Enums\ProjectProvisionStatus;
use App\Jobs\ProvisionProject;
use App\Mcp\Servers\ProjectServer;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\ListProjectDirectoriesTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use App\Models\Task;
use App\Services\ClaudePromptRunner;
use App\Services\ProjectService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath, 0755, true);

    config(['developer-tool.projects_path' => $this->projectsPath]);

    Bus::fake();
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('the server registers all project tools', function () {
    ProjectServer::tool(ListProjectsTool::class);
    ProjectServer::tool(ListProjectDirectoriesTool::class);
    ProjectServer::tool(CreateProjectTool::class);
})->throwsNoExceptions();

test('list-projects returns the projects with their task counts', function () {
    $project = Project::factory()->create(['name' => 'alpha', 'path' => $this->projectsPath.'/alpha']);
    Task::factory()->count(2)->create(['project_id' => $project->id]);

    ProjectServer::tool(ListProjectsTool::class)
        ->assertOk()
        ->assertSee('alpha')
        ->assertSee('tasks_count');
});

test('create-project in new mode creates a ready project with its directory, no job', function () {
    ProjectServer::tool(CreateProjectTool::class, [
        'mode' => 'new',
        'name' => 'fresh',
        'description' => 'Friss projekt',
    ])->assertOk()->assertSee('fresh');

    $project = Project::sole();
    expect($project->name)->toBe('fresh')
        ->and($project->path)->toBe(app(ProjectService::class)->pathFor('fresh'))
        ->and($project->description)->toBe('Friss projekt')
        ->and($project->provision_status)->toBe(ProjectProvisionStatus::Ready)
        ->and(is_dir($project->path))->toBeTrue();

    Bus::assertNotDispatched(ProvisionProject::class);
});

test('create-project in git mode derives the name from the git url', function () {
    ProjectServer::tool(CreateProjectTool::class, [
        'mode' => 'git',
        'git_url' => 'https://github.com/acme/widget-shop.git',
    ])->assertOk();

    $project = Project::sole();
    expect($project->name)->toBe('widget-shop')
        ->and($project->git_url)->toBe('https://github.com/acme/widget-shop.git')
        ->and($project->path)->toBe(app(ProjectService::class)->pathFor('widget-shop'))
        ->and($project->provision_status)->toBe(ProjectProvisionStatus::Pending);

    Bus::assertDispatched(ProvisionProject::class);
});

test('create-project in existing mode registers an available directory as ready without a job', function () {
    mkdir($this->projectsPath.'/legacy');

    ProjectServer::tool(CreateProjectTool::class, [
        'mode' => 'existing',
        'directory' => 'legacy',
    ])->assertOk();

    $project = Project::sole();
    expect($project->name)->toBe('legacy')
        ->and($project->path)->toBe($this->projectsPath.'/legacy')
        ->and($project->provision_status)->toBe(ProjectProvisionStatus::Ready);

    Bus::assertNotDispatched(ProvisionProject::class);
});

test('create-project in existing mode rejects a directory that is not available', function () {
    ProjectServer::tool(CreateProjectTool::class, [
        'mode' => 'existing',
        'directory' => 'missing',
    ])->assertHasErrors();

    expect(Project::count())->toBe(0);
    Bus::assertNotDispatched(ProvisionProject::class);
});

test('create-project in existing mode rejects a directory that is already registered', function () {
    mkdir($this->projectsPath.'/taken');
    Project::factory()->create(['name' => 'taken', 'path' => $this->projectsPath.'/taken']);

    ProjectServer::tool(CreateProjectTool::class, [
        'mode' => 'existing',
        'directory' => 'taken',
    ])->assertHasErrors();

    expect(Project::count())->toBe(1);
});

test('create-project rejects invalid input and creates nothing', function (array $arguments) {
    mkdir($this->projectsPath.'/on-disk');
    Project::factory()->create(['name' => 'duplicate', 'path' => $this->projectsPath.'/duplicate']);

    ProjectServer::tool(CreateProjectTool::class, $arguments)->assertHasErrors();

    expect(Project::count())->toBe(1);
    Bus::assertNotDispatched(ProvisionProject::class);
})->with([
    'duplicate name' => [['mode' => 'new', 'name' => 'duplicate']],
    'name with a slash' => [['mode' => 'new', 'name' => 'foo/bar']],
    'name with a backslash' => [['mode' => 'new', 'name' => 'foo\\bar']],
    'new folder already on disk' => [['mode' => 'new', 'name' => 'on-disk']],
    'new mode without a name' => [['mode' => 'new']],
    'missing mode' => [['name' => 'fresh']],
    'unknown mode' => [['mode' => 'bogus', 'name' => 'fresh']],
    'dot name' => [['mode' => 'new', 'name' => '.']],
    'dot-dot name' => [['mode' => 'new', 'name' => '..']],
    'dot-dot name in git mode' => [['mode' => 'git', 'name' => '..', 'git_url' => 'https://github.com/acme/widget.git']],
    'name with a null byte' => [['mode' => 'new', 'name' => "foo\0bar"]],
    'name with a control character' => [['mode' => 'new', 'name' => "foo\nbar"]],
    'git target folder already on disk' => [['mode' => 'git', 'name' => 'on-disk', 'git_url' => 'https://github.com/acme/widget.git']],
    'git target derived from url already on disk' => [['mode' => 'git', 'git_url' => 'https://github.com/acme/on-disk.git']],
    'git url as an option' => [['mode' => 'git', 'name' => 'fresh', 'git_url' => '--upload-pack=touch /tmp/pwned']],
    'git url with file transport' => [['mode' => 'git', 'name' => 'fresh', 'git_url' => 'file:///etc']],
    'git url with ext transport' => [['mode' => 'git', 'name' => 'fresh', 'git_url' => 'ext::sh -c id']],
    'git url as a local path' => [['mode' => 'git', 'name' => 'fresh', 'git_url' => '/tmp/some-repo']],
    'mode as an array' => [['mode' => ['new'], 'name' => 'fresh']],
    'name as an array' => [['mode' => 'new', 'name' => ['fresh']]],
    'git url as an array' => [['mode' => 'git', 'name' => 'fresh', 'git_url' => ['https://github.com/acme/widget.git']]],
]);

test('create-project accepts ssh and scp-like git urls', function (string $gitUrl) {
    ProjectServer::tool(CreateProjectTool::class, [
        'mode' => 'git',
        'git_url' => $gitUrl,
    ])->assertOk();

    expect(Project::sole()->git_url)->toBe($gitUrl);
})->with([
    'ssh url' => ['ssh://git@github.com:22/acme/widget.git'],
    'scp-like' => ['git@github.com:acme/widget.git'],
]);

test('create-project trims the git url and stores an empty description as null', function () {
    ProjectServer::tool(CreateProjectTool::class, [
        'mode' => 'git',
        'git_url' => '  https://github.com/acme/widget.git  ',
        'description' => '   ',
    ])->assertOk()->assertSee('"provision_status":"pending"');

    $project = Project::sole();
    expect($project->name)->toBe('widget')
        ->and($project->git_url)->toBe('https://github.com/acme/widget.git')
        ->and($project->description)->toBeNull();
});

test('list-projects reports the provisioning status and error', function () {
    Project::factory()->create([
        'name' => 'broken',
        'path' => $this->projectsPath.'/broken',
        'provision_status' => ProjectProvisionStatus::Failed,
        'provision_error' => 'fatal: repository not found',
    ]);

    ProjectServer::tool(ListProjectsTool::class)
        ->assertOk()
        ->assertSee('"provision_status":"failed"')
        ->assertSee('fatal: repository not found');
});

test('create-project cannot set is_system or provision_status', function () {
    // A clone keeps the status `pending`; the injected `provision_status`
    // below must be ignored, so asserting `pending` proves it was not honoured.
    ProjectServer::tool(CreateProjectTool::class, [
        'mode' => 'git',
        'name' => 'sneaky',
        'git_url' => 'https://github.com/acme/sneaky.git',
        'is_system' => true,
        'provision_status' => 'ready',
    ]);

    $project = Project::query()->where('name', 'sneaky')->first();

    expect($project)->not->toBeNull()
        ->and($project->is_system)->toBeFalse()
        ->and($project->provision_status)->toBe(ProjectProvisionStatus::Pending);
});

test('list-project-directories returns only directories that are not registered', function () {
    mkdir($this->projectsPath.'/registered');
    mkdir($this->projectsPath.'/unregistered');
    file_put_contents($this->projectsPath.'/a-file.txt', 'x');
    Project::factory()->create(['name' => 'registered', 'path' => $this->projectsPath.'/registered']);

    ProjectServer::tool(ListProjectDirectoriesTool::class)
        ->assertOk()
        ->assertSee('unregistered')
        ->assertDontSee('a-file.txt')
        ->assertDontSee('"registered"');
});

test('prompt runs are not given the projects mcp server', function () {
    Process::fake();
    $agent = Agent::factory()->create(['tools' => ['Read', 'mcp__tasks__create-task']]);
    $alpha = Project::factory()->create(['name' => 'alpha', 'path' => $this->projectsPath]);
    $run = PromptRun::factory()->inContext(['project_id' => $alpha->id, 'agent_id' => $agent->id])->create();

    app(ClaudePromptRunner::class)->run($run);

    Process::assertRan(function ($process) {
        $command = $process->command;
        $mcpConfig = json_decode($command[array_search('--mcp-config', $command, true) + 1], true);

        return ! array_key_exists('projects', $mcpConfig['mcpServers']);
    });
});

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

test('create-project in new mode creates a pending project and queues provisioning', function () {
    ProjectServer::tool(CreateProjectTool::class, [
        'mode' => 'new',
        'name' => 'fresh',
        'description' => 'Friss projekt',
    ])->assertOk()->assertSee('fresh');

    $project = Project::sole();
    expect($project->name)->toBe('fresh')
        ->and($project->path)->toBe(app(ProjectService::class)->pathFor('fresh'))
        ->and($project->description)->toBe('Friss projekt')
        ->and($project->provision_status)->toBe(ProjectProvisionStatus::Pending);

    Bus::assertDispatched(ProvisionProject::class, fn (ProvisionProject $job) => $job->project->is($project));
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
]);

test('create-project cannot set is_system or provision_status', function () {
    ProjectServer::tool(CreateProjectTool::class, [
        'mode' => 'new',
        'name' => 'sneaky',
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

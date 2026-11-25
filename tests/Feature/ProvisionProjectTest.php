<?php

use App\Enums\ProjectProvisionStatus;
use App\Jobs\ProvisionProject;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->projectsPath = sys_get_temp_dir().'/dev-tool-'.uniqid();
    mkdir($this->projectsPath, 0755, true);

    config(['developer-tool.projects_path' => $this->projectsPath]);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg($this->projectsPath));
});

test('provisioning a new folder creates the directory and marks it ready', function () {
    $project = Project::factory()->create([
        'name' => 'gamma',
        'path' => $this->projectsPath.'/gamma',
        'git_url' => null,
        'provision_status' => ProjectProvisionStatus::Pending,
    ]);

    (new ProvisionProject($project))->handle(app(ProjectService::class));

    expect(is_dir($this->projectsPath.'/gamma'))->toBeTrue()
        ->and($project->fresh()->provision_status)->toBe(ProjectProvisionStatus::Ready);
});

test('a git project is cloned with the url after -- and only the https/ssh transports allowed', function () {
    Process::fake();
    $project = Project::factory()->create([
        'name' => 'widget',
        'path' => $this->projectsPath.'/widget',
        'git_url' => 'https://github.com/acme/widget.git',
        'provision_status' => ProjectProvisionStatus::Pending,
    ]);

    (new ProvisionProject($project))->handle(app(ProjectService::class));

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
        'git', 'clone', '--', 'https://github.com/acme/widget.git', $this->projectsPath.'/widget',
    ] && $process->environment === ['GIT_ALLOW_PROTOCOL' => 'https:ssh']);

    expect($project->fresh()->provision_status)->toBe(ProjectProvisionStatus::Ready);
});

test('a failed clone marks the project as failed with the error', function () {
    Process::fake(['*' => Process::result(errorOutput: 'fatal: repository not found', exitCode: 128)]);
    $project = Project::factory()->create([
        'name' => 'broken',
        'path' => $this->projectsPath.'/broken',
        'git_url' => 'https://github.com/acme/missing.git',
        'provision_status' => ProjectProvisionStatus::Pending,
    ]);

    $job = new ProvisionProject($project);

    // handle() lets the exception bubble so the queue marks the job failed;
    // failed() then records the state on the project.
    try {
        $job->handle(app(ProjectService::class));
    } catch (Throwable $exception) {
        $job->failed($exception);
    }

    $fresh = $project->fresh();

    expect($fresh->provision_status)->toBe(ProjectProvisionStatus::Failed)
        ->and($fresh->provision_error)->toBe('fatal: repository not found')
        ->and(is_dir($this->projectsPath.'/broken'))->toBeFalse();
});

test('a disallowed git url is never passed to git and fails the project', function (string $gitUrl) {
    Process::fake();
    $project = Project::factory()->create([
        'name' => 'evil',
        'path' => $this->projectsPath.'/evil',
        'git_url' => $gitUrl,
        'provision_status' => ProjectProvisionStatus::Pending,
    ]);

    $job = new ProvisionProject($project);

    try {
        $job->handle(app(ProjectService::class));
    } catch (Throwable $exception) {
        $job->failed($exception);
    }

    Process::assertNothingRan();
    expect($project->fresh()->provision_status)->toBe(ProjectProvisionStatus::Failed);
})->with([
    'option injection' => ['--upload-pack=touch /tmp/pwned'],
    'file transport' => ['file:///definitely/not/a/repo'],
    'ext transport' => ['ext::sh -c touch% /tmp/pwned'],
    'local path' => ['/tmp/some-repo'],
]);

test('provisioning refuses a path outside the projects directory', function (string $relativePath, ?string $gitUrl) {
    Process::fake();
    $project = Project::factory()->create([
        'name' => 'escape',
        'path' => $this->projectsPath.$relativePath,
        'git_url' => $gitUrl,
        'provision_status' => ProjectProvisionStatus::Pending,
    ]);

    $job = new ProvisionProject($project);

    try {
        $job->handle(app(ProjectService::class));
    } catch (Throwable $exception) {
        $job->failed($exception);
    }

    Process::assertNothingRan();
    expect($project->fresh()->provision_status)->toBe(ProjectProvisionStatus::Failed)
        ->and(is_dir($this->projectsPath.'/nested'))->toBeFalse();
})->with([
    'parent directory (mkdir)' => ['/..', null],
    'parent directory (clone)' => ['/..', 'https://github.com/acme/widget.git'],
    'nested directory' => ['/nested/deeper', null],
]);

<?php

use App\Enums\ProjectProvisionStatus;
use App\Jobs\ProvisionProject;
use App\Models\Project;
use App\Services\ProjectService;

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

test('a failed clone marks the project as failed with the error', function () {
    $project = Project::factory()->create([
        'name' => 'broken',
        'path' => $this->projectsPath.'/broken',
        'git_url' => 'file:///definitely/not/a/repo',
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
        ->and($fresh->provision_error)->not->toBeNull()
        ->and(is_dir($this->projectsPath.'/broken'))->toBeFalse();
});

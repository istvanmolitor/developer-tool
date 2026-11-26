<?php

namespace App\Jobs;

use App\Enums\ProjectProvisionStatus;
use App\Models\Project;
use App\Repositories\Contracts\ProjectRepository;
use App\Services\ProjectService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProvisionProject implements ShouldQueue
{
    use Queueable;

    /**
     * Cloning a large repository can take a while; give the job room beyond the
     * git clone timeout itself.
     */
    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(public Project $project) {}

    /**
     * Create the project directory on disk: `git clone` when the project was
     * created from a git link, otherwise an empty `mkdir`.
     */
    public function handle(ProjectService $projects): void
    {
        $repository = app(ProjectRepository::class);

        $this->project->forceFill([
            'provision_status' => ProjectProvisionStatus::Provisioning,
            'provision_error' => null,
        ]);
        $repository->save($this->project);

        if ($this->project->git_url) {
            $projects->cloneRepository($this->project->git_url, $this->project->path);
        } else {
            $projects->createDirectory($this->project->path);
        }

        $this->project->forceFill([
            'provision_status' => ProjectProvisionStatus::Ready,
            'provision_error' => null,
        ]);
        $repository->save($this->project);
    }

    public function failed(?Throwable $exception): void
    {
        $this->project->forceFill([
            'provision_status' => ProjectProvisionStatus::Failed,
            'provision_error' => $exception?->getMessage() ?? 'A projekt létrehozása váratlanul meghiúsult.',
        ]);
        app(ProjectRepository::class)->save($this->project);
    }
}

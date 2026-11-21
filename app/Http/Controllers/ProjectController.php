<?php

namespace App\Http\Controllers;

use App\Models\PromptRun;
use App\Services\ProjectService;
use App\Support\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    public function index(): Response
    {
        $projects = $this->projects->all();

        $stats = PromptRun::query()
            ->selectRaw('project, count(*) as runs_count, max(created_at) as last_run_at')
            ->groupBy('project')
            ->get()
            ->keyBy('project');

        return Inertia::render('Projects/Index', [
            'projects' => $projects->map(fn (Project $project): array => [
                ...$project->toArray(),
                'runs_count' => (int) ($stats[$project->name]->runs_count ?? 0),
                'last_run_at' => $stats[$project->name]->last_run_at ?? null,
            ])->all(),
            'projects_path' => $this->projects->projectsPath(),
        ]);
    }

    public function show(Request $request, string $project): Response
    {
        $found = $this->projects->find($project);

        if ($found === null) {
            throw new NotFoundHttpException("Project [{$project}] not found.");
        }

        return Inertia::render('Projects/Show', [
            'project' => $found->toArray(),
            'runs' => PromptRun::query()
                ->where('project', $found->name)
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }
}

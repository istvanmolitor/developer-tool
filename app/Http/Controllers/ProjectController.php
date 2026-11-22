<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\PromptRun;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    public function index(): Response
    {
        $projects = Project::query()
            ->withCount('promptRuns as runs_count')
            ->withMax('promptRuns as last_run_at', 'created_at')
            ->orderBy('name')
            ->get();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'projects_path' => $this->projects->projectsPath(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Projects/Create', [
            'projects_path' => $this->projects->projectsPath(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        Project::create($request->validated());

        return redirect()
            ->route('projects.index')
            ->with('status', 'Projekt létrehozva.');
    }

    public function show(Project $project): Response
    {
        return Inertia::render('Projects/Show', [
            'project' => $project,
            'runs_count' => $project->promptRuns()->count(),
            'last_run_at' => $project->promptRuns()->max('created_at'),
        ]);
    }

    public function prompts(Project $project): Response
    {
        return Inertia::render('Projects/Prompts', [
            'project' => $project,
            'runs' => $project->promptRuns()
                ->latest()
                ->limit(50)
                ->get(),
        ]);
    }

    public function edit(Project $project): Response
    {
        return Inertia::render('Projects/Edit', [
            'project' => $project,
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $validated = $request->validated();

        // Keep prompt-run history attached when a project is renamed, since
        // runs are linked by the project name rather than its id.
        if ($validated['name'] !== $project->name) {
            PromptRun::query()
                ->where('project', $project->name)
                ->update(['project' => $validated['name']]);
        }

        $project->update($validated);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Projekt frissítve.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('status', 'Projekt eltávolítva.');
    }
}

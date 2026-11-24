<?php

namespace App\Http\Controllers;

use App\Enums\ProjectProvisionStatus;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Jobs\ProvisionProject;
use App\Models\Agent;
use App\Models\Project;
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
            'directories' => $this->projects->availableDirectories(),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $mode = $validated['mode'];

        // "existing" points at a directory already on disk, so it is ready
        // straight away. "new"/"git" need the directory created (mkdir/clone),
        // which happens in a background job.
        $needsProvisioning = $mode !== 'existing';

        $project = new Project;
        $project->forceFill([
            'name' => $validated['name'],
            'path' => $validated['path'],
            'git_url' => $mode === 'git' ? ($validated['git_url'] ?? null) : null,
            'description' => $validated['description'] ?? null,
            'provision_status' => $needsProvisioning
                ? ProjectProvisionStatus::Pending
                : ProjectProvisionStatus::Ready,
        ])->save();

        if ($needsProvisioning) {
            ProvisionProject::dispatch($project);
        }

        return redirect()
            ->route('projects.index')
            ->with('status', $needsProvisioning
                ? 'Projekt létrehozva, a mappa előkészítése folyamatban.'
                : 'Projekt létrehozva.');
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
            'agents' => Agent::query()
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
            'runs' => $project->promptRuns()
                ->with('agent:id,name')
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

        // The system project always points at the application itself — its
        // path is fixed and cannot be changed through the edit form.
        if ($project->is_system) {
            $validated['path'] = $project->path;
        }

        $project->update($validated);

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Projekt frissítve.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        if ($project->is_system) {
            return redirect()
                ->route('projects.index')
                ->with('status', 'A rendszer saját projektje nem törölhető.');
        }

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('status', 'Projekt eltávolítva.');
    }
}

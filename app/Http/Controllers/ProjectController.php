<?php

namespace App\Http\Controllers;

use App\Enums\PromptRunStatus;
use App\Enums\SearchType;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Agent;
use App\Models\Project;
use App\Models\PromptRun;
use App\Services\ProjectService;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $project = $this->projects->createProject($request->validated());

        return redirect()
            ->route('projects.index')
            ->with('status', $project->provision_status->isInProgress()
                ? 'Projekt létrehozva, a mappa előkészítése folyamatban.'
                : 'Projekt létrehozva.');
    }

    public function show(Project $project): Response
    {
        return Inertia::render('Projects/Show', [
            'project' => $project,
            'runs_count' => $project->promptRuns()->count(),
            'last_run_at' => $project->promptRuns()->max('prompt_runs.created_at'),
        ]);
    }

    /**
     * The prompt form plus the project's prompt runs from the search index,
     * filterable by text (prompt and output), status and agent, and paginated.
     */
    public function prompts(Request $request, Project $project, SearchService $search): Response
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => PromptRunStatus::tryFrom($request->string('status')->toString())?->value,
            'agent' => $request->integer('agent') ?: null,
        ];

        $results = $search->search(
            SearchCriteria::for(SearchType::Prompt)
                ->matching($filters['q'])
                ->where('project_id', $project->id)
                ->where('status', $filters['status'])
                ->where('agent_ids', $filters['agent'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->integer('page', 1), 20),
        );

        return Inertia::render('Projects/Prompts', [
            'project' => $project,
            'agents' => Agent::query()
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
            'runs' => $results->paginate(PromptRun::query()->with('agent:id,name'), $request),
            'filters' => $filters,
            'search_degraded' => $results->degraded,
            'statuses' => array_map(
                fn (PromptRunStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                PromptRunStatus::cases(),
            ),
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

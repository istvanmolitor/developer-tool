<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Repositories\Contracts\AgentContextRepository;
use App\Repositories\Contracts\AgentRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectService $projects,
        private ProjectRepository $projectRepository,
        private AgentRepository $agents,
        private AgentContextRepository $agentContexts,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Projects/Index', [
            'projects' => $this->projectRepository->allWithRunStats(),
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
    /**
     * How many of the latest messages the project chat shows.
     */
    private const CHAT_MESSAGE_LIMIT = 30;

    /**
     * The project's chat: a live conversation (like the dashboard's), showing
     * the project's latest messages across agents, with an agent picker that
     * continues that agent's conversation on this project.
     */
    public function prompts(Request $request, Project $project): Response
    {
        return Inertia::render('Projects/Prompts', [
            'project' => $project,
            'agents' => $this->agents->allOrderedByName(['id', 'name'])
                ->map->only(['id', 'name'])->values(),
            'runs' => $project->promptRuns()
                ->with('agent:id,name')
                ->latest('prompt_runs.created_at')
                ->limit(self::CHAT_MESSAGE_LIMIT)
                ->get(),
            'contexts' => $this->agentContexts->latestPerAgentForProjectUser($project, $request->user()),
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

        $this->projectRepository->update($project, $validated);

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

        $this->projectRepository->delete($project);

        return redirect()
            ->route('projects.index')
            ->with('status', 'Projekt eltávolítva.');
    }
}

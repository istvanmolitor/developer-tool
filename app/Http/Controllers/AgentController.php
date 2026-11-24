<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\Project;
use App\Services\AgentSyncService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AgentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Agents/Index', [
            'agents' => Agent::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Agents/Create');
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        Agent::create($request->validated());

        return redirect()
            ->route('agents.index')
            ->with('status', 'Agent létrehozva.');
    }

    public function show(Agent $agent): Response
    {
        return Inertia::render('Agents/Show', [
            'agent' => $agent,
            'projects' => Project::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map->only(['id', 'name']),
            'runs' => $agent->promptRuns()
                ->with('project:id,name,path')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * The agent's profile: its model, tools, source and system prompt.
     */
    public function profile(Agent $agent): Response
    {
        return Inertia::render('Agents/Profile', [
            'agent' => $agent,
        ]);
    }

    /**
     * The open tasks assigned to the agent, highest priority first and the
     * oldest first within the same priority.
     */
    public function tasks(Agent $agent): Response
    {
        return Inertia::render('Agents/Tasks', [
            'agent' => $agent->only(['id', 'name', 'slug']),
            'tasks' => $agent->tasks()
                ->with('project:id,name')
                ->where('status', '!=', TaskStatus::Done)
                ->orderByRaw('case priority when ? then 0 when ? then 1 else 2 end', [
                    TaskPriority::High->value,
                    TaskPriority::Medium->value,
                ])
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(),
            'statuses' => collect(TaskStatus::cases())->map(
                fn (TaskStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            ),
            'priorities' => collect(TaskPriority::cases())->map(
                fn (TaskPriority $priority): array => ['value' => $priority->value, 'label' => $priority->label()],
            ),
        ]);
    }

    public function edit(Agent $agent): Response
    {
        return Inertia::render('Agents/Edit', [
            'agent' => $agent,
        ]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): RedirectResponse
    {
        $agent->update($request->validated());

        return redirect()
            ->route('agents.profile', $agent)
            ->with('status', 'Agent frissítve.');
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $agent->delete();

        return redirect()
            ->route('agents.index')
            ->with('status', 'Agent eltávolítva.');
    }

    /**
     * Refresh the agents table from the agent definition files on disk.
     */
    public function sync(AgentSyncService $agentSync): RedirectResponse
    {
        try {
            $synced = $agentSync->sync();

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$synced} agent szinkronizálva."]);
        } catch (RuntimeException $exception) {
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect()->route('agents.index');
    }
}

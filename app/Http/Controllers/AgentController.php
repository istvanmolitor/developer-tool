<?php

namespace App\Http\Controllers;

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
            ->route('agents.show', $agent)
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

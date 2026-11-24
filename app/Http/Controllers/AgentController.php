<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

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
}

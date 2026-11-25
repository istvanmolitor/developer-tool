<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromptRunRequest;
use App\Models\Agent;
use App\Models\AgentContext;
use App\Models\Project;
use App\Services\ClaudePromptRunner;
use Illuminate\Http\RedirectResponse;

class PromptRunController extends Controller
{
    public function __construct(private ClaudePromptRunner $runner) {}

    public function store(StorePromptRunRequest $request, Project $project): RedirectResponse
    {
        $agentId = $request->validated('agent_id');
        $agent = $agentId !== null ? Agent::findOrFail((int) $agentId) : null;

        // A chat continues the latest context of its project, agent and user.
        $context = AgentContext::current($project, $agent, $request->user());

        $this->runner->queueInContext($context, $request->validated('prompt'));

        return back()->with('status', 'Prompt sorba állítva.');
    }
}

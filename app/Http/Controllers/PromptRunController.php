<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromptRunRequest;
use App\Models\AgentContext;
use App\Models\Project;
use App\Repositories\Contracts\AgentContextRepository;
use App\Repositories\Contracts\AgentRepository;
use App\Services\ClaudePromptRunner;
use Illuminate\Http\RedirectResponse;

class PromptRunController extends Controller
{
    public function __construct(
        private ClaudePromptRunner $runner,
        private AgentContextRepository $agentContexts,
        private AgentRepository $agents,
    ) {}

    public function store(StorePromptRunRequest $request, Project $project): RedirectResponse
    {
        $contextId = $request->validated('agent_context_id');

        if ($contextId !== null) {
            // A chosen conversation goes on in its own context; only the user's
            // own conversations of this project can be continued.
            $context = $this->agentContexts->findOwnedInProject($request->user(), $project, (int) $contextId);
        } else {
            $agentId = $request->validated('agent_id');
            $agent = $agentId !== null ? $this->agents->findOrFail((int) $agentId) : null;

            // A chat continues the latest context of its project, agent and user.
            $context = AgentContext::current($project, $agent, $request->user());
        }

        $this->runner->queueInContext($context, $request->validated('prompt'));

        return back()->with('status', 'Prompt sorba állítva.');
    }
}

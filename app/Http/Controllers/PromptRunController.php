<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromptRunRequest;
use App\Models\AgentContext;
use App\Models\Project;
use App\Repositories\Contracts\AgentContextRepository;
use App\Repositories\Contracts\AgentRepository;
use App\Repositories\Contracts\PromptRunRepository;
use App\Repositories\Contracts\SettingRepository;
use App\Services\ClaudePromptRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PromptRunController extends Controller
{
    /**
     * How many pending runs the queue page lists per page.
     */
    private const PER_PAGE = 20;

    public function __construct(
        private ClaudePromptRunner $runner,
        private AgentContextRepository $agentContexts,
        private AgentRepository $agents,
        private PromptRunRepository $promptRuns,
        private SettingRepository $settings,
    ) {}

    /**
     * Show the prompt queue: the runs still waiting to execute, the global pause
     * switch, and the controls to pause/clear them.
     */
    public function index(): Response
    {
        return Inertia::render('PromptRuns/Index', [
            'runs' => $this->promptRuns->paginatePending(self::PER_PAGE),
            'paused' => $this->settings->flag('pause_prompt_runs'),
        ]);
    }

    /**
     * Flip the global pause switch. While on, pending runs stay queued without
     * executing; turning it off lets the worker pick them up again.
     */
    public function pause(Request $request): RedirectResponse
    {
        $this->settings->setFlag('pause_prompt_runs', $request->boolean('paused'));

        return back();
    }

    /**
     * Discard every run still waiting in the queue — a clean slate after a
     * run-away loop flooded it.
     */
    public function clearPending(): RedirectResponse
    {
        $deleted = $this->promptRuns->deletePending();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$deleted} függő prompt törölve."]);

        return back();
    }

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

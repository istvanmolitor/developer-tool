<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromptRunRequest;
use App\Models\Agent;
use App\Models\Project;
use App\Services\ClaudePromptRunner;
use Illuminate\Http\RedirectResponse;

class PromptRunController extends Controller
{
    public function __construct(private ClaudePromptRunner $runner) {}

    public function store(StorePromptRunRequest $request, Project $project): RedirectResponse
    {
        $agentId = $request->validated('agent_id');
        $prompt = $request->validated('prompt');

        if ($agentId !== null) {
            $this->runner->queueAgent($project, Agent::findOrFail((int) $agentId), $prompt, $request->user());
        } else {
            $this->runner->queue($project, $prompt, $request->user());
        }

        return back()->with('status', 'Prompt sorba állítva.');
    }
}

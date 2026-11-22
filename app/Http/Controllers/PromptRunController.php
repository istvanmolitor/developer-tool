<?php

namespace App\Http\Controllers;

use App\Enums\PromptRunStatus;
use App\Http\Requests\StorePromptRunRequest;
use App\Jobs\RunClaudePrompt;
use App\Models\Project;
use App\Models\PromptRun;
use Illuminate\Http\RedirectResponse;

class PromptRunController extends Controller
{
    public function store(StorePromptRunRequest $request, Project $project): RedirectResponse
    {
        $run = PromptRun::create([
            'project' => $project->name,
            'prompt' => $request->validated('prompt'),
            'status' => PromptRunStatus::Pending,
        ]);

        RunClaudePrompt::dispatch($run);

        return back()->with('status', 'Prompt queued.');
    }
}

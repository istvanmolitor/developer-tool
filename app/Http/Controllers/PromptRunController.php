<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePromptRunRequest;
use App\Models\Project;
use App\Services\ClaudePromptRunner;
use Illuminate\Http\RedirectResponse;

class PromptRunController extends Controller
{
    public function __construct(private ClaudePromptRunner $runner) {}

    public function store(StorePromptRunRequest $request, Project $project): RedirectResponse
    {
        $this->runner->queue($project, $request->validated('prompt'), $request->user());

        return back()->with('status', 'Prompt sorba állítva.');
    }
}

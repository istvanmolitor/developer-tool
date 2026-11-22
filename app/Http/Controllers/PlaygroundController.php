<?php

namespace App\Http\Controllers;

use App\Enums\PromptRunStatus;
use App\Http\Requests\StorePlaygroundRunRequest;
use App\Jobs\RunClaudePrompt;
use App\Models\Project;
use App\Models\PromptRun;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PlaygroundController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    public function index(): Response
    {
        return Inertia::render('Playground', [
            'projects' => $this->projects->all()->map(fn (Project $project): string => $project->name)->all(),
            'runs' => PromptRun::query()->latest()->limit(10)->get(),
        ]);
    }

    public function store(StorePlaygroundRunRequest $request): RedirectResponse
    {
        $run = PromptRun::create([
            'project' => $request->validated('project'),
            'prompt' => $request->validated('prompt'),
            'status' => PromptRunStatus::Pending,
        ]);

        RunClaudePrompt::dispatch($run);

        return back()->with('status', 'Prompt queued.');
    }
}

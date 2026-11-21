<?php

namespace App\Http\Controllers;

use App\Enums\PromptRunStatus;
use App\Http\Requests\StorePromptRunRequest;
use App\Jobs\RunClaudePrompt;
use App\Models\PromptRun;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PromptRunController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    public function store(StorePromptRunRequest $request, string $project): RedirectResponse
    {
        $found = $this->projects->find($project);

        if ($found === null) {
            throw new NotFoundHttpException("Project [{$project}] not found.");
        }

        $run = PromptRun::create([
            'project' => $found->name,
            'prompt' => $request->validated('prompt'),
            'status' => PromptRunStatus::Pending,
        ]);

        RunClaudePrompt::dispatch($run);

        return back()->with('status', 'Prompt queued.');
    }
}

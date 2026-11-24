<?php

namespace App\Http\Controllers;

use App\Enums\PromptRunStatus;
use App\Models\PromptRun;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How many queued prompts the dashboard lists by name; the rest only count.
     */
    private const QUEUED_LIST_LIMIT = 10;

    /**
     * Show the dashboard with the prompts running right now and the ones still
     * waiting in the queue, in the order the worker will pick them up.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'promptQueue' => fn (): array => [
                'running' => $this->runsWithStatus(PromptRunStatus::Running)
                    ->orderBy('started_at')
                    ->get(),
                'queued' => $this->runsWithStatus(PromptRunStatus::Pending)
                    ->orderBy('id')
                    ->limit(self::QUEUED_LIST_LIMIT)
                    ->get(),
                'queued_count' => PromptRun::query()
                    ->where('status', PromptRunStatus::Pending)
                    ->count(),
            ],
        ]);
    }

    /**
     * @return Builder<PromptRun>
     */
    private function runsWithStatus(PromptRunStatus $status): Builder
    {
        return PromptRun::query()
            ->where('status', $status)
            ->with(['project:id,name', 'agent:id,name'])
            ->select(['id', 'project_id', 'agent_id', 'prompt', 'status', 'started_at', 'created_at']);
    }
}

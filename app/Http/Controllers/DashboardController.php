<?php

namespace App\Http\Controllers;

use App\Enums\PromptRunStatus;
use App\Models\Agent;
use App\Models\AgentContext;
use App\Models\Project;
use App\Models\PromptRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * How many queued prompts the dashboard lists by name; the rest only count.
     */
    private const QUEUED_LIST_LIMIT = 10;

    /**
     * How many of the latest messages the dashboard chat shows.
     */
    private const CHAT_MESSAGE_LIMIT = 30;

    /**
     * How many of the user's latest conversations the dashboard lists beside the chat.
     */
    private const RECENT_CONTEXT_LIMIT = 20;

    /**
     * Show the dashboard with the prompts running right now, the ones still
     * waiting in the queue (in the order the worker will pick them up) and a
     * chat with the chosen project and agent (or the chosen conversation).
     */
    public function __invoke(Request $request): Response
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
            'chat' => fn (): array => $this->chat($request),
        ]);
    }

    /**
     * The dashboard chat: the projects and agents to choose from, the chosen
     * ones (by default the system project without an agent), the latest
     * messages of the user's current conversation with them and the user's
     * latest conversations to switch to. A chosen conversation ("context")
     * decides the project and agent itself.
     *
     * @return array{
     *     projects: list<array{id: int, name: string}>,
     *     agents: list<array{id: int, name: string}>,
     *     project: string|null,
     *     agent_id: int|null,
     *     context: array{id: int, session_id: string|null}|null,
     *     context_chosen: bool,
     *     runs: list<PromptRun>,
     *     recent_contexts: list<array{id: int, project: string, agent: string|null, title: string|null, last_activity_at: string|null}>,
     * }
     */
    private function chat(Request $request): array
    {
        $projects = Project::query()->orderBy('name')->get(['id', 'name', 'is_system']);
        $agents = Agent::query()->orderBy('name')->get(['id', 'name']);

        $chosenContext = $request->filled('context') ? AgentContext::query()
            ->where('user_id', $request->user()->id)
            ->whereNotNull('project_id')
            ->find($request->integer('context'), ['id', 'project_id', 'agent_id', 'session_id']) : null;

        if ($chosenContext !== null) {
            $project = $projects->firstWhere('id', $chosenContext->project_id);
            $agent = $agents->firstWhere('id', $chosenContext->agent_id);
            $context = $chosenContext;
        } else {
            $project = $projects->firstWhere('name', $request->string('project')->toString())
                ?? $projects->firstWhere('is_system', true)
                ?? $projects->first();
            $agent = $agents->firstWhere('id', $request->integer('agent'));

            // Only look the conversation up: the first message creates it.
            $context = $project === null ? null : AgentContext::query()
                ->where('project_id', $project->id)
                ->where('agent_id', $agent?->id)
                ->where('user_id', $request->user()->id)
                ->latest('id')
                ->first(['id', 'session_id']);
        }

        return [
            'projects' => $projects->map->only(['id', 'name'])->values()->all(),
            'agents' => $agents->map->only(['id', 'name'])->values()->all(),
            'project' => $project?->name,
            'agent_id' => $agent?->id,
            'context' => $context?->only(['id', 'session_id']),
            'context_chosen' => $chosenContext !== null,
            'runs' => $context === null ? [] : $context->promptRuns()
                ->latest('id')
                ->limit(self::CHAT_MESSAGE_LIMIT)
                ->get(['id', 'agent_context_id', 'prompt', 'status', 'exit_code', 'output', 'error_output', 'created_at'])
                ->reverse()
                ->values()
                ->all(),
            'recent_contexts' => $this->recentContexts($request),
        ];
    }

    /**
     * The user's latest conversations that have a message, the most recently
     * active first, titled by their first prompt.
     *
     * @return list<array{id: int, project: string, agent: string|null, title: string|null, last_activity_at: string|null}>
     */
    private function recentContexts(Request $request): array
    {
        return AgentContext::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('project')
            ->whereHas('promptRuns')
            ->with(['project:id,name', 'agent:id,name'])
            ->withMax('promptRuns', 'id')
            ->withMax('promptRuns', 'created_at')
            ->addSelect(['title' => PromptRun::query()
                ->select('prompt')
                ->whereColumn('agent_context_id', 'agent_contexts.id')
                ->orderBy('id')
                ->limit(1),
            ])
            ->orderByDesc('prompt_runs_max_id')
            ->limit(self::RECENT_CONTEXT_LIMIT)
            ->get()
            ->map(function (AgentContext $context): array {
                $title = $context->getAttribute('title');
                $lastActivityAt = $context->getAttribute('prompt_runs_max_created_at');

                return [
                    'id' => $context->id,
                    'project' => $context->project->name,
                    'agent' => $context->agent?->name,
                    'title' => is_string($title) ? $title : null,
                    'last_activity_at' => $lastActivityAt === null ? null : Carbon::parse($lastActivityAt)->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return Builder<PromptRun>
     */
    private function runsWithStatus(PromptRunStatus $status): Builder
    {
        return PromptRun::query()
            ->where('status', $status)
            ->with(['project:id,name', 'agent:id,name'])
            ->select(['id', 'agent_context_id', 'prompt', 'status', 'started_at', 'created_at']);
    }
}

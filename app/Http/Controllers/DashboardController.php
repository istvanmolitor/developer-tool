<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\AgentContext;
use App\Models\PromptRun;
use App\Repositories\Contracts\AgentContextRepository;
use App\Repositories\Contracts\AgentRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Repositories\Contracts\PromptRunRepository;
use App\Repositories\Contracts\SettingRepository;
use App\Repositories\Contracts\TaskRepository;
use Carbon\CarbonImmutable;
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

    public function __construct(
        private PromptRunRepository $promptRuns,
        private ProjectRepository $projects,
        private AgentRepository $agents,
        private AgentContextRepository $agentContexts,
        private TaskRepository $tasks,
        private SettingRepository $settings,
    ) {}

    /**
     * Show the dashboard with the prompts running right now, the ones still
     * waiting in the queue (in the order the worker will pick them up) and a
     * chat with the chosen project and agent (or the chosen conversation).
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'promptQueue' => fn (): array => [
                'running' => $this->promptRuns->running(),
                'queued' => $this->promptRuns->queued(self::QUEUED_LIST_LIMIT),
                'queued_count' => $this->promptRuns->pendingCount(),
            ],
            'chat' => fn (): array => $this->chat($request),
            'taskStats' => fn (): array => $this->tasks->dashboardCounts(),
            'costStats' => fn (): array => $this->costStats(),
        ]);
    }

    /**
     * What the prompts have cost today and this week, with the number of runs
     * behind each figure and the limits they are measured against.
     *
     * @return array{
     *     daily: array{cost: float, runs: int, limit: float|null},
     *     weekly: array{cost: float, runs: int, limit: float|null},
     * }
     */
    private function costStats(): array
    {
        return [
            'daily' => [
                ...$this->promptRuns->usageSince(CarbonImmutable::now()->startOfDay()),
                'limit' => $this->costLimit('daily_cost_limit_usd'),
            ],
            'weekly' => [
                ...$this->promptRuns->usageSince(CarbonImmutable::now()->startOfWeek()),
                'limit' => $this->costLimit('weekly_cost_limit_usd'),
            ],
        ];
    }

    /**
     * A spending limit from the settings, or null when none is set.
     */
    private function costLimit(string $key): ?float
    {
        $value = $this->settings->get($key);

        return $value === null || $value === '' ? null : (float) $value;
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
        $projects = $this->projects->allOrderedByName(['id', 'name', 'is_system']);
        $agents = $this->agents->allOrderedByName(['id', 'name']);

        $chosenContext = $request->filled('context')
            ? $this->agentContexts->findForUser($request->user(), $request->integer('context'), ['id', 'project_id', 'agent_id', 'session_id'])
            : null;

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
            $context = $project === null ? null : $this->agentContexts->currentForUser(
                $project,
                $agent,
                $request->user(),
                ['id', 'session_id'],
            );
        }

        return [
            'projects' => $projects->map->only(['id', 'name'])->values()->all(),
            'agents' => $agents->map->only(['id', 'name'])->values()->all(),
            'project' => $project?->name,
            'agent_id' => $agent?->id,
            'context' => $context?->only(['id', 'session_id']),
            'context_chosen' => $chosenContext !== null,
            'runs' => $context === null ? [] : $context->promptRuns()
                ->with('agent:id,name,avatar_path')
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
        return $this->agentContexts->recentForUser($request->user(), self::RECENT_CONTEXT_LIMIT)
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
}

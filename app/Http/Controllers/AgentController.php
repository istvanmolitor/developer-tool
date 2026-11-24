<?php

namespace App\Http\Controllers;

use App\Enums\SearchType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\Project;
use App\Models\Task;
use App\Services\AgentSyncService;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class AgentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Agents/Index', [
            'agents' => Agent::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Agents/Create');
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        Agent::create($request->validated());

        return redirect()
            ->route('agents.index')
            ->with('status', 'Agent létrehozva.');
    }

    public function show(Agent $agent): Response
    {
        return Inertia::render('Agents/Show', [
            'agent' => $agent,
            'projects' => Project::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map->only(['id', 'name']),
            'runs' => $agent->promptRuns()
                ->with('project:id,name,path')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    /**
     * The agent's profile: its model, tools, source and system prompt.
     */
    public function profile(Agent $agent): Response
    {
        return Inertia::render('Agents/Profile', [
            'agent' => $agent,
        ]);
    }

    /**
     * The tasks assigned to the agent from the search index — by default the
     * open ones — highest priority first and the oldest first within the same
     * priority; filterable by text, status, priority and project, and paginated.
     */
    public function tasks(Request $request, Agent $agent, SearchService $search): Response
    {
        $status = $request->string('status')->toString();

        $filters = [
            'q' => trim($request->string('q')->toString()),
            // "open" (the default) hides done tasks, "all" shows every status.
            'status' => $status === 'all' || TaskStatus::tryFrom($status) !== null ? $status : 'open',
            'priority' => TaskPriority::tryFrom($request->string('priority')->toString())?->value,
            'project' => $request->integer('project') ?: null,
        ];

        $criteria = SearchCriteria::for(SearchType::Task)
            ->matching($filters['q'])
            ->where('agent_ids', $agent->id)
            ->where('priority', $filters['priority'])
            ->where('project_id', $filters['project'])
            ->orderBy('priority_rank')
            ->orderBy('created_at')
            ->paginate($request->integer('page', 1), 20);

        match ($filters['status']) {
            'open' => $criteria->whereNot('status', TaskStatus::Done->value),
            'all' => null,
            default => $criteria->where('status', $filters['status']),
        };

        $results = $search->search($criteria);

        return Inertia::render('Agents/Tasks', [
            'agent' => $agent->only(['id', 'name', 'slug']),
            'tasks' => $results->paginate(Task::query()->with('project:id,name'), $request),
            'filters' => $filters,
            'search_degraded' => $results->degraded,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name'])->map->only(['id', 'name']),
            'statuses' => collect(TaskStatus::cases())->map(
                fn (TaskStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            ),
            'priorities' => collect(TaskPriority::cases())->map(
                fn (TaskPriority $priority): array => ['value' => $priority->value, 'label' => $priority->label()],
            ),
        ]);
    }

    /**
     * The agents this agent exchanges messages with, one conversation per partner
     * agent and project (most recent first), plus the selected conversation's messages.
     */
    public function messages(Request $request, Agent $agent): Response
    {
        $partnerSql = 'case when sender_agent_id = ? then recipient_agent_id else sender_agent_id end';

        $conversations = AgentMessage::query()
            ->where(fn ($query) => $query
                ->where('sender_agent_id', $agent->id)
                ->orWhere('recipient_agent_id', $agent->id))
            ->select('project_id')
            ->selectRaw("{$partnerSql} as partner_id", [$agent->id])
            ->selectRaw('count(*) as message_count')
            ->selectRaw('sum(case when recipient_agent_id = ? and is_read = ? then 1 else 0 end) as unread_count', [$agent->id, false])
            ->selectRaw('max(id) as last_message_id')
            ->groupBy('project_id', 'partner_id')
            ->orderByDesc('last_message_id')
            ->toBase()
            ->get();

        $partners = Agent::query()->whereKey($conversations->pluck('partner_id')->filter())->get(['id', 'name', 'slug'])->keyBy('id');
        $projects = Project::query()->whereKey($conversations->pluck('project_id'))->get(['id', 'name'])->keyBy('id');
        $lastMessages = AgentMessage::query()->whereKey($conversations->pluck('last_message_id'))->get()->keyBy('id');

        $conversations = $conversations->map(fn (object $conversation): array => [
            'partner' => $partners->get($conversation->partner_id)?->only(['id', 'name', 'slug']),
            'project' => $projects->get($conversation->project_id)?->only(['id', 'name']),
            'message_count' => (int) $conversation->message_count,
            'unread_count' => (int) $conversation->unread_count,
            'last_message' => $lastMessages->get($conversation->last_message_id)?->only(['body', 'sender_agent_id', 'created_at']),
        ])->values();

        $selected = $conversations->first(fn (array $conversation): bool => $conversation['project']['id'] === $request->integer('project')
            && ($conversation['partner']['id'] ?? null) === $request->integer('partner')) ?? $conversations->first();

        return Inertia::render('Agents/Messages', [
            'agent' => $agent->only(['id', 'name', 'slug']),
            'conversations' => $conversations,
            'selected' => $selected === null ? null : [
                'partner_id' => $selected['partner']['id'] ?? null,
                'project_id' => $selected['project']['id'],
            ],
            'messages' => $selected === null ? [] : $this->conversationMessages($agent, $selected['project']['id'], $selected['partner']['id'] ?? null),
        ]);
    }

    /**
     * The latest messages between the agent and a partner in a project, oldest first.
     * A null partner stands for agents that have since been deleted.
     *
     * @return list<array{id: int, body: string, outgoing: bool, is_read: bool, read_at: string|null, created_at: string|null}>
     */
    private function conversationMessages(Agent $agent, int $projectId, ?int $partnerId): array
    {
        return AgentMessage::query()
            ->where('project_id', $projectId)
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('sender_agent_id', $agent->id)->where('recipient_agent_id', $partnerId))
                ->orWhere(fn ($query) => $query->where('recipient_agent_id', $agent->id)
                    ->when(
                        $partnerId === null,
                        fn ($query) => $query->whereNull('sender_agent_id'),
                        fn ($query) => $query->where('sender_agent_id', $partnerId),
                    )))
            ->latest('id')
            ->limit(200)
            ->get()
            ->reverse()
            ->map(fn (AgentMessage $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'outgoing' => $message->sender_agent_id === $agent->id,
                'is_read' => $message->is_read,
                'read_at' => $message->read_at?->toIso8601String(),
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function edit(Agent $agent): Response
    {
        return Inertia::render('Agents/Edit', [
            'agent' => $agent,
        ]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): RedirectResponse
    {
        $agent->update($request->validated());

        return redirect()
            ->route('agents.profile', $agent)
            ->with('status', 'Agent frissítve.');
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $agent->delete();

        return redirect()
            ->route('agents.index')
            ->with('status', 'Agent eltávolítva.');
    }

    /**
     * Refresh the agents table from the agent definition files on disk.
     */
    public function sync(AgentSyncService $agentSync): RedirectResponse
    {
        try {
            $synced = $agentSync->sync();

            Inertia::flash('toast', ['type' => 'success', 'message' => "{$synced} agent szinkronizálva."]);
        } catch (RuntimeException $exception) {
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect()->route('agents.index');
    }
}

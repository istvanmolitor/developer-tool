<?php

namespace App\Http\Controllers;

use App\Enums\SearchType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreAgentRequest;
use App\Http\Requests\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\Task;
use App\Repositories\Contracts\AgentContextRepository;
use App\Repositories\Contracts\AgentMessageRepository;
use App\Repositories\Contracts\AgentRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Services\AgentAvatarService;
use App\Services\AgentSyncService;
use App\Services\AgentToolService;
use App\Services\Search\SearchCriteria;
use App\Services\Search\SearchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentController extends Controller
{
    public function __construct(
        private AgentToolService $tools,
        private AgentRepository $agents,
        private ProjectRepository $projects,
        private AgentContextRepository $agentContexts,
        private AgentMessageRepository $messages,
        private AgentAvatarService $avatars,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Agents/Index', [
            'agents' => $this->agents->allOrderedByName(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Agents/Create', [
            'tool_groups' => $this->tools->groups(),
        ]);
    }

    public function store(StoreAgentRequest $request, AgentSyncService $agentSync): RedirectResponse
    {
        $agent = $this->agents->create($request->safe()->except(['avatar', 'remove_avatar']));

        $this->applyAvatar($request, $agent);

        $this->writeDefinition($agentSync, $agent);

        return redirect()
            ->route('agents.index')
            ->with('status', 'Agent létrehozva.');
    }

    /**
     * The chat with the agent: its latest runs and, per project, the context the
     * current user's chat continues.
     */
    public function show(Request $request, Agent $agent): Response
    {
        return Inertia::render('Agents/Show', [
            'agent' => $agent,
            'projects' => $this->projects->allOrderedByName(['id', 'name'])
                ->map->only(['id', 'name']),
            'runs' => $agent->promptRuns()
                ->with(['project:id,name,path', 'agent:id,name,avatar_path'])
                ->latest('prompt_runs.created_at')
                ->limit(20)
                ->get(),
            'contexts' => $this->agentContexts->latestPerProjectForAgentUser($agent, $request->user()),
        ]);
    }

    /**
     * The agent's profile picture, streamed from the private disk. Only raster
     * images are ever stored, so serving it inline is safe.
     */
    public function avatar(Agent $agent): StreamedResponse
    {
        $disk = $this->avatars->disk();

        abort_if($agent->avatar_path === null || ! $disk->exists($agent->avatar_path), 404);

        return $disk->response($agent->avatar_path, null, [
            'Content-Type' => $disk->mimeType($agent->avatar_path) ?: 'image/png',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * The agent's profile: its model, tools, source and system prompt.
     */
    public function profile(Agent $agent): Response
    {
        return Inertia::render('Agents/Profile', [
            'agent' => $agent,
            // The tools with their Hungarian names, so the profile shows what the
            // agent may do rather than the raw tool names.
            'tools' => $this->tools->describe($agent->tools),
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
            // "open" (the default) hides done and closed tasks, "all" shows every status.
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
            'open' => $criteria->whereNot('status', [TaskStatus::Done->value, TaskStatus::Closed->value]),
            'all' => null,
            default => $criteria->where('status', $filters['status']),
        };

        $results = $search->search($criteria);

        return Inertia::render('Agents/Tasks', [
            'agent' => $agent->only(['id', 'name', 'slug']),
            // Hydration builder for the search-result paginator.
            'tasks' => $results->paginate(Task::query()->with('project:id,name'), $request),
            'filters' => $filters,
            'search_degraded' => $results->degraded,
            'projects' => $this->projects->allOrderedByName(['id', 'name'])->map->only(['id', 'name']),
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
        $conversations = $this->messages->conversationSummariesFor($agent);

        $partners = $this->agents->byIdsKeyed($conversations->pluck('partner_id')->filter()->all(), ['id', 'name', 'slug']);
        $projects = $this->projects->byIdsKeyed($conversations->pluck('project_id')->all(), ['id', 'name']);
        $lastMessages = $this->messages->byIdsKeyed($conversations->pluck('last_message_id')->all());

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
        return $this->messages->thread($agent, $projectId, $partnerId)
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
            'tool_groups' => $this->tools->groups(),
        ]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent, AgentSyncService $agentSync): RedirectResponse
    {
        $previousSlug = $agent->slug;

        $this->agents->update($agent, $request->safe()->except(['avatar', 'remove_avatar']));

        $this->applyAvatar($request, $agent);

        $this->writeDefinition($agentSync, $agent, $previousSlug);

        return redirect()
            ->route('agents.profile', $agent)
            ->with('status', 'Agent frissítve.');
    }

    public function destroy(Agent $agent, AgentSyncService $agentSync): RedirectResponse
    {
        $this->agents->delete($agent);

        try {
            $agentSync->deleteFile($agent);
        } catch (\Throwable $exception) {
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => 'Az agent törölve, de a definíciós fájl eltávolítása nem sikerült.']);
        }

        return redirect()
            ->route('agents.index')
            ->with('status', 'Agent eltávolítva.');
    }

    /**
     * Store the uploaded profile picture, or drop the current one when the form
     * asked for its removal. The picture is not part of the `*.md` definition:
     * it lives only in the database and on the private disk.
     */
    private function applyAvatar(StoreAgentRequest|UpdateAgentRequest $request, Agent $agent): void
    {
        if ($request->hasFile('avatar')) {
            $this->avatars->store($agent, $request->file('avatar'));

            return;
        }

        if ($request->boolean('remove_avatar')) {
            $this->avatars->remove($agent);
        }
    }

    /**
     * Write the agent back out to its definition file, so admin edits land in the
     * `*.md` files too. A write failure is reported and surfaced as a toast, but
     * does not undo the database change the user already made.
     */
    private function writeDefinition(AgentSyncService $agentSync, Agent $agent, ?string $previousSlug = null): void
    {
        try {
            $agentSync->writeFile($agent, $previousSlug);
        } catch (\Throwable $exception) {
            report($exception);

            Inertia::flash('toast', ['type' => 'error', 'message' => 'Az agent mentve, de a definíciós fájl írása nem sikerült.']);
        }
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

<?php

namespace App\Repositories\Eloquent;

use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\Project;
use App\Repositories\Contracts\AgentMessageRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * @extends EloquentRepository<AgentMessage>
 */
class EloquentAgentMessageRepository extends EloquentRepository implements AgentMessageRepository
{
    protected function model(): string
    {
        return AgentMessage::class;
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function conversationSummariesFor(Agent $agent): Collection
    {
        $partnerSql = 'case when sender_agent_id = ? then recipient_agent_id else sender_agent_id end';

        return $this->newQuery()
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
    }

    public function byIdsKeyed(array $ids): EloquentCollection
    {
        return $this->newQuery()->whereKey($ids)->get()->keyBy('id');
    }

    public function thread(Agent $agent, int $projectId, ?int $partnerId, int $limit = 200): EloquentCollection
    {
        return $this->newQuery()
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
            ->limit($limit)
            ->get();
    }

    public function inboxFor(Project $project, Agent $recipient, bool $unreadOnly, int $limit): EloquentCollection
    {
        return $this->newQuery()
            ->with(['sender:id,name', 'recipient:id,name'])
            ->whereBelongsTo($project)
            ->where('recipient_agent_id', $recipient->id)
            ->when($unreadOnly, fn ($query) => $query->unread())
            ->when(
                $unreadOnly,
                fn ($query) => $query->oldest('id'),
                fn ($query) => $query->latest('id'),
            )
            ->limit($limit)
            ->get();
    }

    public function markRead(array $ids): int
    {
        return $this->newQuery()->whereKey($ids)->unread()->update(['is_read' => true, 'read_at' => now()]);
    }

    public function unreadCountFor(Project $project, Agent $recipient): int
    {
        return $this->newQuery()
            ->whereBelongsTo($project)
            ->where('recipient_agent_id', $recipient->id)
            ->unread()
            ->count();
    }
}

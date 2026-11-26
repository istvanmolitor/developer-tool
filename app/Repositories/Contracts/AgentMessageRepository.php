<?php

namespace App\Repositories\Contracts;

use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Data access for {@see AgentMessage} rows.
 *
 * @extends Repository<AgentMessage>
 */
interface AgentMessageRepository extends Repository
{
    /**
     * One row per conversation partner and project the agent has exchanged
     * messages in — message/unread counts and the last message id, most
     * recent conversation first. A base (non-Eloquent) collection of plain
     * rows, as produced by the grouping query.
     *
     * @return Collection<int, \stdClass>
     */
    public function conversationSummariesFor(Agent $agent): Collection;

    /**
     * Messages for the given id set, keyed by id.
     *
     * @param  array<int, int|string>  $ids
     * @return EloquentCollection<int, AgentMessage>
     */
    public function byIdsKeyed(array $ids): EloquentCollection;

    /**
     * The latest messages between the agent and a partner in a project,
     * newest first. A null partner stands for agents that have since been
     * deleted.
     *
     * @return EloquentCollection<int, AgentMessage>
     */
    public function thread(Agent $agent, int $projectId, ?int $partnerId, int $limit = 200): EloquentCollection;

    /**
     * Messages addressed to the recipient within the project, optionally
     * unread only, oldest first when unread only else newest first.
     *
     * @return EloquentCollection<int, AgentMessage>
     */
    public function inboxFor(Project $project, Agent $recipient, bool $unreadOnly, int $limit): EloquentCollection;

    /**
     * Mark the given messages as read, skipping the ones already read.
     *
     * @param  array<int, int|string>  $ids
     * @return int the number of rows updated
     */
    public function markRead(array $ids): int;

    /**
     * How many unread messages the recipient has in the project.
     */
    public function unreadCountFor(Project $project, Agent $recipient): int;
}

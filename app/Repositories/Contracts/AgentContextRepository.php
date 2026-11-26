<?php

namespace App\Repositories\Contracts;

use App\Models\Agent;
use App\Models\AgentContext;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Eloquent\EloquentRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for {@see AgentContext} rows.
 *
 * The model's own start()/current()/startNew() factory methods create and
 * continue contexts; this repository owns the read-side lookups built on top
 * of {@see EloquentRepository::newQuery()}.
 *
 * @extends Repository<AgentContext>
 */
interface AgentContextRepository extends Repository
{
    /**
     * A context owned by the user that has a project, by id — the one the
     * dashboard chat may resume.
     *
     * @param  array<int, string>  $columns
     */
    public function findForUser(User $user, int|string $id, array $columns = ['*']): ?AgentContext;

    /**
     * The user's latest context for a project and agent, if any — the chat
     * continues this one, or starts a new one.
     *
     * @param  array<int, string>  $columns
     */
    public function currentForUser(Project $project, ?Agent $agent, User $user, array $columns = ['*']): ?AgentContext;

    /**
     * The user's latest conversations that have a message, most recently
     * active first, titled by their first prompt — the dashboard's conversation
     * switcher.
     *
     * @return Collection<int, AgentContext>
     */
    public function recentForUser(User $user, int $limit): Collection;

    /**
     * The agent's latest context per project for the given user — the
     * conversations the agent's profile page offers to resume.
     *
     * @return Collection<int, AgentContext>
     */
    public function latestPerProjectForAgentUser(Agent $agent, User $user): Collection;

    /**
     * A context owned by the user within the given project, or throw — a
     * chosen conversation a new prompt is queued into.
     */
    public function findOwnedInProject(User $user, Project $project, int|string $id): AgentContext;
}

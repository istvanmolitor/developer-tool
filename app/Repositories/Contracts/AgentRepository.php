<?php

namespace App\Repositories\Contracts;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for {@see Agent} rows.
 *
 * @extends Repository<Agent>
 */
interface AgentRepository extends Repository
{
    /**
     * Every agent, alphabetical.
     *
     * @param  array<int, string>  $columns
     * @return Collection<int, Agent>
     */
    public function allOrderedByName(array $columns = ['*']): Collection;

    /**
     * Resolve an agent by name or slug, falling back to a numeric id.
     */
    public function resolveByIdentifier(string $identifier): ?Agent;

    /**
     * Agent names keyed by id, for the given id set.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, string>
     */
    public function namesByIds(array $ids): array;

    /**
     * Agents for the given id set, keyed by id.
     *
     * @param  array<int, int|string>  $ids
     * @param  array<int, string>  $columns
     * @return Collection<int, Agent>
     */
    public function byIdsKeyed(array $ids, array $columns = ['*']): Collection;

    /**
     * Upsert an agent from its markdown definition, keyed by slug.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsertBySlug(string $slug, array $attributes): Agent;
}

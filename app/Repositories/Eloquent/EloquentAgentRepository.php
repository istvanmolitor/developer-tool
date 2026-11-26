<?php

namespace App\Repositories\Eloquent;

use App\Models\Agent;
use App\Repositories\Contracts\AgentRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<Agent>
 */
class EloquentAgentRepository extends EloquentRepository implements AgentRepository
{
    protected function model(): string
    {
        return Agent::class;
    }

    public function allOrderedByName(array $columns = ['*']): Collection
    {
        return $this->newQuery()->orderBy('name')->get($columns);
    }

    public function resolveByIdentifier(string $identifier): ?Agent
    {
        return $this->newQuery()
            ->where('name', $identifier)
            ->orWhere('slug', $identifier)
            ->when(
                is_numeric($identifier),
                fn ($query) => $query->orWhere('id', (int) $identifier),
            )
            ->first();
    }

    public function namesByIds(array $ids): array
    {
        return $this->newQuery()->whereKey($ids)->pluck('name', 'id')->all();
    }

    public function byIdsKeyed(array $ids, array $columns = ['*']): Collection
    {
        return $this->newQuery()->whereKey($ids)->get($columns)->keyBy('id');
    }

    public function upsertBySlug(string $slug, array $attributes): Agent
    {
        return $this->newQuery()->updateOrCreate(['slug' => $slug], $attributes);
    }
}

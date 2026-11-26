<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<Project>
 */
class EloquentProjectRepository extends EloquentRepository implements ProjectRepository
{
    protected function model(): string
    {
        return Project::class;
    }

    public function allWithRunStats(): Collection
    {
        return $this->newQuery()
            ->withCount('promptRuns as runs_count')
            ->withMax('promptRuns as last_run_at', 'created_at')
            ->orderBy('name')
            ->get();
    }

    public function allOrderedByName(array $columns = ['*']): Collection
    {
        return $this->newQuery()->orderBy('name')->get($columns);
    }

    public function allWithTaskCounts(): Collection
    {
        return $this->newQuery()->withCount('tasks')->orderBy('name')->get();
    }

    public function findByName(string $name): ?Project
    {
        return $this->newQuery()->where('name', $name)->first();
    }

    public function resolveByNameOrId(string $identifier): ?Project
    {
        return $this->newQuery()
            ->where('name', $identifier)
            ->when(
                is_numeric($identifier),
                fn ($query) => $query->orWhere('id', (int) $identifier),
            )
            ->first();
    }

    public function names(): array
    {
        return $this->newQuery()->pluck('name')->all();
    }

    public function namesByIds(array $ids): array
    {
        return $this->newQuery()->whereKey($ids)->pluck('name', 'id')->all();
    }

    public function byIdsKeyed(array $ids, array $columns = ['*']): Collection
    {
        return $this->newQuery()->whereKey($ids)->get($columns)->keyBy('id');
    }
}

<?php

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Models\TaskChange;
use App\Repositories\Contracts\TaskChangeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends EloquentRepository<TaskChange>
 */
class EloquentTaskChangeRepository extends EloquentRepository implements TaskChangeRepository
{
    protected function model(): string
    {
        return TaskChange::class;
    }

    public function forProjectPaginated(Project $project, int $perPage = 50): LengthAwarePaginator
    {
        return $this->newQuery()
            ->whereHas('task', fn ($query) => $query->whereBelongsTo($project))
            ->with(['task:id,title', 'user:id,name', 'agent:id,name'])
            ->latest('id')
            ->paginate($perPage);
    }
}

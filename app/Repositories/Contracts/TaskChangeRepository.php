<?php

namespace App\Repositories\Contracts;

use App\Models\Project;
use App\Models\TaskChange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Data access for {@see TaskChange} rows.
 *
 * Change rows are normally reached through a task (`$task->taskChanges()`);
 * this repository owns the one top-level read — a project's whole change log.
 *
 * @extends Repository<TaskChange>
 */
interface TaskChangeRepository extends Repository
{
    /**
     * The change log of all of a project's tasks, newest first, paginated, with
     * the task, user and agent of each entry eager-loaded.
     *
     * @return LengthAwarePaginator<int, TaskChange>
     */
    public function forProjectPaginated(Project $project, int $perPage = 50): LengthAwarePaginator;
}

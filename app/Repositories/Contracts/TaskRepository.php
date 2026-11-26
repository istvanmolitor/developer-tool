<?php

namespace App\Repositories\Contracts;

use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

/**
 * Data access for {@see Task} rows.
 *
 * Tasks are also reached as children of a project (`$project->tasks()`); those
 * relationship-scoped reads/writes stay on the relation. This repository owns
 * the top-level task lookups.
 *
 * @extends Repository<Task>
 */
interface TaskRepository extends Repository
{
    /**
     * An agent's open tasks (optionally within one project), ordered
     * in-progress-then-todo, then by priority and position.
     *
     * @return Collection<int, Task>
     */
    public function openForAgent(Agent $agent, ?Project $project = null): Collection;

    /**
     * A single task with the full relationship graph used by the task detail
     * MCP tool (project, agent, comments, changes and attachments with authors).
     */
    public function findWithDetails(int|string $id): ?Task;
}

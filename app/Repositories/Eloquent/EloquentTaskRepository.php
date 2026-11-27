<?php

namespace App\Repositories\Eloquent;

use App\Enums\TaskStatus;
use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\TaskRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends EloquentRepository<Task>
 */
class EloquentTaskRepository extends EloquentRepository implements TaskRepository
{
    protected function model(): string
    {
        return Task::class;
    }

    public function openForAgent(Agent $agent, ?Project $project = null): Collection
    {
        return $this->newQuery()
            ->where('agent_id', $agent->id)
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Closed->value])
            ->when($project !== null, fn ($query) => $query->where('project_id', $project->id))
            // In-progress before todo; within that, higher priority, then the
            // manual order (position), finally the older task.
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'todo' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->orderBy('position')
            ->orderBy('id')
            ->with('project')
            ->get();
    }

    public function findWithDetails(int|string $id): ?Task
    {
        return $this->newQuery()
            ->with([
                'project',
                'agent',
                'comments.user:id,name',
                'comments.agent:id,name',
                'taskChanges.user:id,name',
                'taskChanges.agent:id,name',
                'attachments.project',
                'attachments.tasks:id',
            ])
            ->whereKey($id)
            ->first();
    }

    public function dashboardCounts(): array
    {
        return [
            'total' => $this->newQuery()->count(),
            'in_progress' => $this->newQuery()->where('status', TaskStatus::InProgress)->count(),
        ];
    }
}

<?php

namespace App\Mcp\Concerns;

use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;

trait FormatsTasks
{
    /**
     * Resolve a project by its name (preferred) or numeric id.
     */
    protected function resolveProject(string $project): ?Project
    {
        return Project::query()
            ->where('name', $project)
            ->when(is_numeric($project), fn ($query) => $query->orWhere('id', (int) $project))
            ->first();
    }

    /**
     * Resolve an agent by its name (preferred) or numeric id.
     */
    protected function resolveAgent(string $agent): ?Agent
    {
        return Agent::query()
            ->where('name', $agent)
            ->when(is_numeric($agent), fn ($query) => $query->orWhere('id', (int) $agent))
            ->first();
    }

    /**
     * Shape a task into a compact array for MCP responses.
     *
     * @return array<string, mixed>
     */
    protected function taskToArray(Task $task): array
    {
        return [
            'id' => $task->id,
            'project' => $task->project->name ?? null,
            'agent' => $task->agent?->name,
            'agent_id' => $task->agent_id,
            'title' => $task->title,
            'type' => $task->type->value,
            'description' => $task->description,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'due_date' => $task->due_date?->toDateString(),
            'position' => $task->position,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }
}

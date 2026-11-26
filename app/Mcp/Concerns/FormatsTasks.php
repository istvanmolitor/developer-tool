<?php

namespace App\Mcp\Concerns;

use App\Models\Agent;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\AgentRepository;
use App\Repositories\Contracts\ProjectRepository;

trait FormatsTasks
{
    /**
     * Resolve a project by its name (preferred) or numeric id.
     */
    protected function resolveProject(string $project): ?Project
    {
        return app(ProjectRepository::class)->resolveByNameOrId($project);
    }

    /**
     * Resolve an agent by its name (preferred), slug or numeric id.
     */
    protected function resolveAgent(string $agent): ?Agent
    {
        return app(AgentRepository::class)->resolveByIdentifier($agent);
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
            'created_by' => $task->creator,
            'title' => $task->title,
            'type' => $task->type->value,
            'description' => $task->description,
            'status' => $task->status->value,
            'auto_run' => $task->auto_run,
            'priority' => $task->priority->value,
            'due_date' => $task->due_date?->toDateString(),
            'position' => $task->position,
            'created_at' => $task->created_at?->toIso8601String(),
            'updated_at' => $task->updated_at?->toIso8601String(),
        ];
    }
}

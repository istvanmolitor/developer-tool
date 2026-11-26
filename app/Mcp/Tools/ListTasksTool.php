<?php

namespace App\Mcp\Tools;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Mcp\Concerns\FormatsTasks;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rules\Enum;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-tasks')]
#[Title('Feladatok listázása')]
#[Description('Egy projekt feladatainak listázása, prioritás és sorrend szerint rendezve. Opcionálisan szűrhető státuszra (draft, todo, in_progress, done, closed) és típusra (pl. bug).')]
class ListTasksTool extends Tool
{
    use FormatsTasks;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project' => ['required', 'string'],
            'status' => ['nullable', 'in:draft,todo,in_progress,done,closed'],
            'type' => ['nullable', new Enum(TaskType::class)],
        ]);

        $project = $this->resolveProject($validated['project']);

        if ($project === null) {
            return Response::error("Nincs „{$validated['project']}” nevű projekt. Használd a list-projects eszközt a nevekhez.");
        }

        $tasks = $project->tasks()
            ->when(
                isset($validated['status']),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->when(
                isset($validated['type']),
                fn ($query) => $query->where('type', $validated['type']),
            )
            ->orderBy('position')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Task $task): array => $this->taskToArray($task))
            ->all();

        return Response::json([
            'project' => $project->name,
            'count' => count($tasks),
            'tasks' => $tasks,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('A projekt neve (vagy azonosítója), amelynek feladatait kéred.')
                ->required(),
            'status' => $schema->string()
                ->description('Opcionális státusz-szűrő.')
                ->enum(array_map(fn (TaskStatus $status): string => $status->value, TaskStatus::cases())),
            'type' => $schema->string()
                ->description('Opcionális típus-szűrő.')
                ->enum(array_map(fn (TaskType $type): string => $type->value, TaskType::cases())),
        ];
    }
}

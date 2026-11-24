<?php

namespace App\Mcp\Tools;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Mcp\Concerns\FormatsTasks;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rules\Enum;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-task')]
#[Title('Feladat létrehozása')]
#[Description('Új feladat létrehozása egy projekthez. Csak a cím kötelező; a státusz alapból "todo", a prioritás "medium", a típus "development".')]
class CreateTaskTool extends Tool
{
    use FormatsTasks;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', new Enum(TaskType::class)],
            'agent' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'in:todo,in_progress,done'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
        ]);

        $project = $this->resolveProject($validated['project']);

        if ($project === null) {
            return Response::error("Nincs „{$validated['project']}” nevű projekt. Használd a list-projects eszközt a nevekhez.");
        }

        $agentId = null;

        if (! empty($validated['agent'])) {
            $agent = $this->resolveAgent($validated['agent']);

            if ($agent === null) {
                return Response::error("Nincs „{$validated['agent']}” nevű agent. Használd a list-agents eszközt a nevekhez.");
            }

            $agentId = $agent->id;
        }

        $task = $project->tasks()->create([
            'agent_id' => $agentId,
            'title' => $validated['title'],
            'type' => $validated['type'] ?? TaskType::Development->value,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? TaskStatus::Todo->value,
            'priority' => $validated['priority'] ?? TaskPriority::Medium->value,
            'due_date' => $validated['due_date'] ?? null,
        ]);

        return Response::json([
            'message' => 'Feladat létrehozva.',
            'task' => $this->taskToArray($task->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('A projekt neve (vagy azonosítója), amelyhez a feladat tartozik.')
                ->required(),
            'title' => $schema->string()
                ->description('A feladat címe.')
                ->required(),
            'type' => $schema->string()
                ->description('A feladat típusa (development = fejlesztés, design, bug = hiba, documentation = dokumentáció, testing = tesztelés, other = egyéb). Alapértelmezett: development.')
                ->enum(array_map(fn (TaskType $type): string => $type->value, TaskType::cases())),
            'agent' => $schema->string()
                ->description('Opcionális: a feladathoz rendelt agent neve (vagy azonosítója). A nevekhez használd a list-agents eszközt.'),
            'description' => $schema->string()
                ->description('Opcionális részletes leírás.'),
            'status' => $schema->string()
                ->description('Kezdő státusz. Alapértelmezett: todo.')
                ->enum(array_map(fn (TaskStatus $status): string => $status->value, TaskStatus::cases())),
            'priority' => $schema->string()
                ->description('Prioritás. Alapértelmezett: medium.')
                ->enum(array_map(fn (TaskPriority $priority): string => $priority->value, TaskPriority::cases())),
            'due_date' => $schema->string()
                ->description('Opcionális határidő ÉÉÉÉ-HH-NN formátumban.')
                ->format('date'),
        ];
    }
}

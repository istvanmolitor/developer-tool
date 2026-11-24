<?php

namespace App\Mcp\Tools;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Mcp\Concerns\FormatsTasks;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('update-task')]
#[Title('Feladat módosítása')]
#[Description('Egy meglévő feladat módosítása az azonosítója alapján. Csak a megadott mezők változnak. Státusz állításához (pl. "done") is ezt használd.')]
class UpdateTaskTool extends Tool
{
    use FormatsTasks;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'agent' => ['sometimes', 'nullable', 'string'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', 'in:todo,in_progress,done'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
        ]);

        $task = Task::whereKey($validated['task_id'])->first();

        if ($task === null) {
            return Response::error("Nincs {$validated['task_id']} azonosítójú feladat.");
        }

        // The `agent` argument takes an agent name/id, not the agent_id column
        // directly. An empty string clears the assignment.
        if ($request->has('agent')) {
            $agentName = (string) $validated['agent'];

            if ($agentName === '') {
                $task->agent_id = null;
            } else {
                $agent = $this->resolveAgent($agentName);

                if ($agent === null) {
                    return Response::error("Nincs „{$agentName}” nevű agent. Használd a list-agents eszközt a nevekhez.");
                }

                $task->agent_id = $agent->id;
            }
        }

        $task->fill(collect($validated)->except(['task_id', 'agent'])->all());
        $task->save();

        return Response::json([
            'message' => 'Feladat frissítve.',
            'task' => $this->taskToArray($task->refresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()
                ->description('A módosítandó feladat azonosítója.')
                ->required(),
            'title' => $schema->string()
                ->description('Új cím.'),
            'agent' => $schema->string()
                ->description('A hozzárendelt agent neve (vagy azonosítója). Üres szöveggel a hozzárendelés törölhető. A nevekhez használd a list-agents eszközt.'),
            'description' => $schema->string()
                ->description('Új leírás (üres szöveggel törölhető).'),
            'status' => $schema->string()
                ->description('Új státusz.')
                ->enum(array_map(fn (TaskStatus $status): string => $status->value, TaskStatus::cases())),
            'priority' => $schema->string()
                ->description('Új prioritás.')
                ->enum(array_map(fn (TaskPriority $priority): string => $priority->value, TaskPriority::cases())),
            'due_date' => $schema->string()
                ->description('Új határidő ÉÉÉÉ-HH-NN formátumban.')
                ->format('date'),
        ];
    }
}

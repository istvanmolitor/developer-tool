<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('delete-task')]
#[Title('Feladat törlése')]
#[Description('Egy feladat végleges törlése az azonosítója alapján.')]
class DeleteTaskTool extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer'],
        ]);

        $task = Task::whereKey($validated['task_id'])->first();

        if ($task === null) {
            return Response::error("Nincs {$validated['task_id']} azonosítójú feladat.");
        }

        $task->delete();

        return Response::json([
            'message' => "A(z) {$validated['task_id']} azonosítójú feladat törölve.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()
                ->description('A törlendő feladat azonosítója.')
                ->required(),
        ];
    }
}

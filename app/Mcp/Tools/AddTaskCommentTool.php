<?php

namespace App\Mcp\Tools;

use App\Mcp\Servers\TaskServer;
use App\Repositories\Contracts\TaskRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('add-task-comment')]
#[Title('Hozzászólás írása')]
#[Description('Hozzászólás írása egy feladat alá (pl. haladás, kérdés, eredmény összefoglalása). Agent futás közben a hozzászólás az agent nevén jelenik meg.')]
class AddTaskCommentTool extends Tool
{
    public function handle(Request $request, TaskRepository $tasks): Response
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $task = $tasks->find($validated['task_id']);

        if ($task === null) {
            return Response::error("Nincs {$validated['task_id']} azonosítójú feladat.");
        }

        $comment = $task->comments()->create([
            'agent_id' => TaskServer::currentAgentId(),
            'body' => $validated['body'],
        ]);

        return Response::json([
            'message' => 'Hozzászólás elmentve.',
            'comment' => $comment->load('agent:id,name')->toDisplayArray(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()
                ->description('A feladat azonosítója.')
                ->required(),
            'body' => $schema->string()
                ->description('A hozzászólás szövege.')
                ->required(),
        ];
    }
}

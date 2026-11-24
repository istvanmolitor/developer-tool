<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-task-comments')]
#[Title('Hozzászólások listázása')]
#[Description('Egy feladat hozzászólásainak listázása időrendben (legrégebbi elöl), a szerzővel (felhasználó vagy agent) együtt.')]
#[IsReadOnly]
class ListTaskCommentsTool extends Tool
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

        $comments = $task->comments()
            ->with(['user:id,name', 'agent:id,name'])
            ->get()
            ->map(fn (TaskComment $comment): array => $comment->toDisplayArray());

        return Response::json([
            'task_id' => $task->id,
            'count' => $comments->count(),
            'comments' => $comments->all(),
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
        ];
    }
}

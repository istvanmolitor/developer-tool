<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsAttachments;
use App\Mcp\Servers\TaskServer;
use App\Models\Attachment;
use App\Models\TaskComment;
use App\Repositories\Contracts\TaskRepository;
use App\Services\TaskChangeFormatter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('get-task')]
#[Title('Feladat beolvasása')]
#[Description('Egy feladat teljes kontextusának beolvasása az azonosítója alapján: a feladat adatai, a projekt, a felelős agent (és hogy rád van-e osztva), a teljes hozzászólás-előzmény (ki mit csinált a feladattal), a változásnapló és a csatolt fájlok (abszolút útvonallal). Amikor egy feladaton dolgozol, EZT hívd meg először — ebből minden kiderül, ami a feladathoz tartozik, és hogy mi vonatkozik rád.')]
#[IsReadOnly]
class GetTaskTool extends Tool
{
    use FormatsAttachments;

    public function handle(Request $request, TaskChangeFormatter $changes, TaskRepository $tasks): Response
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer'],
        ]);

        $task = $tasks->findWithDetails($validated['task_id']);

        if ($task === null) {
            return Response::error("Nincs {$validated['task_id']} azonosítójú feladat.");
        }

        // The agent of the current run, so the agent can tell at a glance whether
        // the task is assigned to it ("mi vonatkozik rám") or it is reading
        // someone else's task for context.
        $currentAgentId = TaskServer::currentAgentId();

        return Response::json([
            'task' => $this->taskToArray($task) + [
                'type_label' => $task->type->label(),
                'status_label' => $task->status->label(),
                'priority_label' => $task->priority->label(),
            ],
            'project' => [
                'name' => $task->project->name ?? null,
                'path' => $task->project->path ?? null,
            ],
            'assigned_agent' => $task->agent?->name,
            'assigned_to_you' => $currentAgentId !== null && $task->agent_id === $currentAgentId,
            'comments' => $task->comments
                ->map(fn (TaskComment $comment): array => $comment->toDisplayArray())
                ->all(),
            'changes' => $changes->format($task->taskChanges),
            'attachments' => $task->attachments
                ->map(fn (Attachment $attachment): array => $this->attachmentToArray($attachment))
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()
                ->description('A beolvasandó feladat azonosítója.')
                ->required(),
        ];
    }
}

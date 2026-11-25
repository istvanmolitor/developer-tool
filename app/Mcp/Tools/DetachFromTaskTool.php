<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsAttachments;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('detach-from-task')]
#[Title('Csatolmány leválasztása feladatról')]
#[Description('Egy csatolmány és egy feladat kapcsolatát bontja. A csatolmány fájlja és sora megmarad, csak a feladathoz kötés szűnik meg.')]
class DetachFromTaskTool extends Tool
{
    use FormatsAttachments;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer'],
            'attachment_id' => ['required', 'integer'],
        ]);

        $task = $this->resolveTask($validated['task_id']);

        if ($task === null) {
            return Response::error("Nincs {$validated['task_id']} azonosítójú feladat.");
        }

        $attachment = $this->resolveAttachment($validated['attachment_id']);

        if ($attachment === null) {
            return Response::error("Nincs {$validated['attachment_id']} azonosítójú csatolmány.");
        }

        $task->attachments()->detach($attachment->id);

        return Response::json([
            'message' => "A(z) „{$attachment->filename}” csatolmány leválasztva a(z) „{$task->title}” feladatról.",
            'task_id' => $task->id,
            'attachment_id' => $attachment->id,
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
            'attachment_id' => $schema->integer()
                ->description('A leválasztandó csatolmány azonosítója.')
                ->required(),
        ];
    }
}

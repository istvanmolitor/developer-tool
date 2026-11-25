<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsAttachments;
use App\Services\AttachmentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('attach-to-task')]
#[Title('Csatolmány feladathoz kötése')]
#[Description('Egy meglévő csatolmányt egy feladathoz köt, hogy az agent a feladatnál hivatkozhasson rá. A csatolmány azonosítóját a create-attachment vagy a list-attachments adja.')]
class AttachToTaskTool extends Tool
{
    use FormatsAttachments;

    public function handle(Request $request, AttachmentService $attachments): Response
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

        $attachments->attachToTask($attachment, $task);

        return Response::json([
            'message' => "A(z) „{$attachment->filename}” csatolmány a(z) „{$task->title}” feladathoz kötve.",
            'task_id' => $task->id,
            'attachment' => $this->attachmentToArray($attachment->load('tasks:id')),
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
                ->description('A csatolandó csatolmány azonosítója.')
                ->required(),
        ];
    }
}

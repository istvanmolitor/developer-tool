<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsAttachments;
use App\Models\Attachment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-attachments')]
#[Title('Csatolmányok listázása')]
#[Description('Egy projekt vagy egy task csatolmányait listázza: azonosító, fájlnév, méret, típus, forrás és a tárolt abszolút útvonal, amelyre hivatkozni lehet. Add meg a project vagy a task_id paramétert (ha a task_id megvan, a projekt nem kell).')]
class ListAttachmentsTool extends Tool
{
    use FormatsAttachments;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project' => ['nullable', 'string'],
            'task_id' => ['nullable', 'integer'],
        ]);

        if (! empty($validated['task_id'])) {
            $task = $this->resolveTask($validated['task_id']);

            if ($task === null) {
                return Response::error("Nincs {$validated['task_id']} azonosítójú feladat.");
            }

            $attachments = $task->attachments()->with(['project:id,name', 'tasks:id'])->orderBy('attachments.id')->get();

            return Response::json([
                'task_id' => $task->id,
                'attachments' => $attachments->map(fn (Attachment $a): array => $this->attachmentToArray($a))->all(),
            ]);
        }

        if (empty($validated['project'])) {
            return Response::error('Add meg a project vagy a task_id paramétert.');
        }

        $project = $this->resolveProject($validated['project']);

        if ($project === null) {
            return Response::error("Nincs „{$validated['project']}” nevű projekt. Használd a list-projects eszközt a nevekhez.");
        }

        $attachments = $project->attachments()->with('tasks:id')->orderBy('id')->get();

        return Response::json([
            'project' => $project->name,
            'attachments' => $attachments->map(fn (Attachment $a): array => $this->attachmentToArray($a))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()
                ->description('A projekt neve (vagy azonosítója), amelynek a csatolmányait listázzuk.'),
            'task_id' => $schema->integer()
                ->description('Egy feladat azonosítója; ekkor a feladathoz kötött csatolmányokat kapod vissza.'),
        ];
    }
}

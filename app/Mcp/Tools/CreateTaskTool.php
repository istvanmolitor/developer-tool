<?php

namespace App\Mcp\Tools;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Mcp\Concerns\FormatsAttachments;
use App\Models\Attachment;
use App\Repositories\Contracts\AttachmentRepository;
use App\Services\AttachmentService;
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
#[Description('Új feladat létrehozása egy projekthez. Csak a cím kötelező; a státusz alapból "todo", a prioritás "medium", a típus "development". Ha a feladathoz csatolmányok tartoznak (pl. a levél mellékletei, amiből a feladat született), add meg az azonosítóikat az attachment_ids mezőben, és rögtön a feladathoz kötődnek.')]
class CreateTaskTool extends Tool
{
    use FormatsAttachments;

    public function handle(Request $request, AttachmentService $attachments, AttachmentRepository $attachmentRepository): Response
    {
        $validated = $request->validate([
            'project' => ['required', 'string'],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', new Enum(TaskType::class)],
            'agent' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'in:draft,todo,in_progress,done,closed'],
            'auto_run' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'due_date' => ['nullable', 'date'],
            'attachment_ids' => ['nullable', 'array'],
            'attachment_ids.*' => ['integer'],
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

        // Resolve the related attachments before creating anything, so an invalid
        // id fails fast instead of leaving a task with no files behind.
        $attachmentIds = array_values(array_unique($validated['attachment_ids'] ?? []));
        $related = $attachmentRepository->byIds($attachmentIds);

        if (count($related) !== count($attachmentIds)) {
            $missing = array_diff($attachmentIds, $related->pluck('id')->all());

            return Response::error('Nincs ilyen azonosítójú csatolmány: '.implode(', ', $missing).'. Használd a list-attachments eszközt a létező csatolmányokhoz.');
        }

        $foreign = $related->filter(
            fn (Attachment $attachment): bool => $attachment->project_id !== null && $attachment->project_id !== $project->id,
        );

        if ($foreign->isNotEmpty()) {
            return Response::error('A(z) '.$foreign->pluck('id')->join(', ')." csatolmány másik projekthez tartozik, nem köthető a(z) „{$project->name}” projekt feladatához.");
        }

        $task = $project->tasks()->create([
            'agent_id' => $agentId,
            'title' => $validated['title'],
            'type' => $validated['type'] ?? TaskType::Development->value,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? TaskStatus::Todo->value,
            'auto_run' => $validated['auto_run'] ?? false,
            'priority' => $validated['priority'] ?? TaskPriority::Medium->value,
            'due_date' => $validated['due_date'] ?? null,
        ]);

        foreach ($related as $attachment) {
            $attachments->attachToTask($attachment, $task);
        }

        $task->load('attachments');

        return Response::json([
            'message' => $related->isEmpty()
                ? 'Feladat létrehozva.'
                : "Feladat létrehozva, {$related->count()} csatolmánnyal.",
            'task' => $this->taskToArray($task),
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
            'auto_run' => $schema->boolean()
                ->description('Automatikus végrehajtás: ha igaz, a feladat automatikus futtatásra van jelölve. Alapértelmezett: false.'),
            'priority' => $schema->string()
                ->description('Prioritás. Alapértelmezett: medium.')
                ->enum(array_map(fn (TaskPriority $priority): string => $priority->value, TaskPriority::cases())),
            'due_date' => $schema->string()
                ->description('Opcionális határidő ÉÉÉÉ-HH-NN formátumban.')
                ->format('date'),
            'attachment_ids' => $schema->array()
                ->description('Opcionális: a feladathoz kötendő, már létező csatolmányok azonosítói (pl. a levél mellékletei, amiből a feladat született). Az azonosítókat a list-attachments vagy a create-attachment adja. Minden csatolmánynak a feladat projektjéhez (vagy egyik projekthez sem) kell tartoznia.')
                ->items($schema->integer()),
        ];
    }
}

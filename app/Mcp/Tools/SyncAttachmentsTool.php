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

#[Name('sync-attachments')]
#[Title('Csatolmányok betöltése a mappából')]
#[Description('Betölti a projekt csatolmány-mappájának fájljait a csatolmány-táblába (a fájlokat a privát lemezre másolja, útvonal szerint upsertel, a törölt fájlok sorai eltűnnek). A csatolmány-mappa (a projektkönyvtárak melletti közös attachments mappa projekt-almappája) a szerkeszthető forrás; hívd ezt, miután oda fájlt tettél vagy módosítottál.')]
class SyncAttachmentsTool extends Tool
{
    use FormatsAttachments;

    public function handle(Request $request, AttachmentService $attachments): Response
    {
        $validated = $request->validate([
            'project' => ['required', 'string'],
        ]);

        $project = $this->resolveProject($validated['project']);

        if ($project === null) {
            return Response::error("Nincs „{$validated['project']}” nevű projekt. Használd a list-projects eszközt a nevekhez.");
        }

        $synced = $attachments->syncProject($project);

        return Response::json([
            'message' => "{$synced->count()} csatolmány betöltve a lemezről.",
            'project' => $project->name,
            'attachments' => $synced
                ->map(fn ($attachment): array => $this->attachmentToArray($attachment))
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
                ->description('A projekt neve (vagy azonosítója), amelynek a csatolmány-mappáját betöltjük. A nevekhez használd a list-projects eszközt.')
                ->required(),
        ];
    }
}

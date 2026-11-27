<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsTasks;
use App\Services\DocumentService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('sync-documents')]
#[Title('Dokumentumok betöltése az adatbázisba')]
#[Description('Betölti a projekt docs mappájának összes Markdown fájlját — az almappákkal együtt — a dokumentumok táblába (upsert útvonal szerint, a törölt fájlok sorai eltűnnek). A fájlok megírása után hívd, hogy a tartalom kereshető legyen az alkalmazásban.')]
class SyncDocumentsTool extends Tool
{
    use FormatsTasks;

    public function handle(Request $request, DocumentService $documents): Response
    {
        $validated = $request->validate([
            'project' => ['required', 'string'],
        ]);

        $project = $this->resolveProject($validated['project']);

        if ($project === null) {
            return Response::error("Nincs „{$validated['project']}” nevű projekt. Használd a list-projects eszközt a nevekhez.");
        }

        $synced = $documents->syncProject($project);

        return Response::json([
            'message' => "{$synced->count()} dokumentum betöltve a lemezről.",
            'project' => $project->name,
            'documents' => $synced
                ->map(fn ($document): array => [
                    'path' => $document->path,
                    'title' => $document->title,
                ])
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
                ->description('A projekt neve (vagy azonosítója), amelynek a docs mappáját betöltjük. A nevekhez használd a list-projects eszközt.')
                ->required(),
        ];
    }
}

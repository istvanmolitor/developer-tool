<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsTasks;
use App\Services\DocumentationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('sync-documentation')]
#[Title('Dokumentáció betöltése az adatbázisba')]
#[Description('Betölti a projekt docs/documentations mappájának Markdown fájljait a dokumentációs táblába (upsert útvonal szerint, a törölt fájlok sorai eltűnnek). A dokumentáció megírása (fájlok létrehozása) után hívd, hogy a tartalom kereshető legyen az alkalmazásban.')]
class SyncDocumentationTool extends Tool
{
    use FormatsTasks;

    public function handle(Request $request, DocumentationService $documentations): Response
    {
        $validated = $request->validate([
            'project' => ['required', 'string'],
        ]);

        $project = $this->resolveProject($validated['project']);

        if ($project === null) {
            return Response::error("Nincs „{$validated['project']}” nevű projekt. Használd a list-projects eszközt a nevekhez.");
        }

        $synced = $documentations->syncProject($project);

        return Response::json([
            'message' => "{$synced->count()} dokumentáció betöltve a lemezről.",
            'project' => $project->name,
            'documentations' => $synced
                ->map(fn ($documentation): array => [
                    'path' => $documentation->path,
                    'title' => $documentation->title,
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
                ->description('A projekt neve (vagy azonosítója), amelynek a docs/documentations mappáját betöltjük. A nevekhez használd a list-projects eszközt.')
                ->required(),
        ];
    }
}

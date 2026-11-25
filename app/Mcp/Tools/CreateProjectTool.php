<?php

namespace App\Mcp\Tools;

use App\Services\ProjectService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('create-project')]
#[Title('Projekt létrehozása')]
#[Description('Új projekt létrehozása a projektek könyvtárában. Módok: "existing" (már meglévő, még nem regisztrált mappa, a `directory` paraméterrel), "new" (új üres mappa, a `name` kötelező), "git" (repó klónozása a `git_url`-ből; a név elhagyható, ekkor az URL-ből származik). A "new" és "git" mappa előkészítése háttérben fut.')]
class CreateProjectTool extends Tool
{
    public function __construct(private ProjectService $projects) {}

    public function handle(Request $request): Response
    {
        $input = $request->all();
        $input = array_merge($input, $this->projects->resolveNameAndPath($input));

        $validated = Validator::validate(
            $input,
            $this->projects->creationRules($input['mode'] ?? null),
            $this->projects->creationMessages(),
        );

        $project = $this->projects->createProject($validated);
        $isPending = $validated['mode'] !== 'existing';

        return Response::json([
            'message' => $isPending
                ? 'Projekt létrehozva, a mappa előkészítése a háttérben folyamatban van.'
                : 'Projekt létrehozva.',
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'path' => $project->path,
                'provision_status' => $project->getRawOriginal('provision_status'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'mode' => $schema->string()
                ->description('A létrehozás módja: existing = meglévő mappa (directory), new = új üres mappa (name), git = klónozás (git_url).')
                ->enum(['existing', 'new', 'git'])
                ->required(),
            'name' => $schema->string()
                ->description('A projekt (mappa) neve, perjel nélkül. "new" módban kötelező; "git" módban opcionális (alapból a repó neve); "existing" módban a directory a név.'),
            'directory' => $schema->string()
                ->description('Csak "existing" módban kötelező: a projektek könyvtárának egy még nem regisztrált mappája. A választható mappákhoz használd a list-project-directories eszközt.'),
            'git_url' => $schema->string()
                ->description('Opcionális, "git" módban: a klónozandó repó URL-je. Üresen hagyva üres mappa jön létre.'),
            'description' => $schema->string()
                ->description('Opcionális leírás a projekthez.'),
        ];
    }
}

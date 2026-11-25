<?php

namespace App\Mcp\Tools;

use App\Services\ProjectService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-project-directories')]
#[Title('Választható mappák listázása')]
#[Description('Listázza a projektek könyvtárának még nem regisztrált mappáit. Ezek közül választhatsz a create-project eszköz "existing" módjában (directory paraméter).')]
class ListProjectDirectoriesTool extends Tool
{
    public function __construct(private ProjectService $projects) {}

    public function handle(Request $request): Response
    {
        return Response::json([
            'projects_path' => $this->projects->projectsPath(),
            'directories' => $this->projects->availableDirectories(),
        ]);
    }
}

<?php

namespace App\Mcp\Tools;

use App\Models\Project;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-projects')]
#[Title('Projektek listázása')]
#[Description('Listázza az összes projektet (név, azonosító, feladatok száma). A projekt nevével hivatkozz rá a többi feladat-eszközben.')]
class ListProjectsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $projects = Project::query()
            ->withCount('tasks')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'tasks_count' => $project->tasks_count,
            ])
            ->all();

        return Response::json(['projects' => $projects]);
    }
}

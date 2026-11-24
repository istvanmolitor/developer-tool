<?php

namespace App\Mcp\Tools;

use App\Models\Agent;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

#[Name('list-agents')]
#[Title('Agentek listázása')]
#[Description('Listázza a hozzárendelhető agenteket (név, azonosító). Az agent nevével rendelhetsz feladatot hozzá a create-task / update-task eszközökben.')]
class ListAgentsTool extends Tool
{
    public function handle(Request $request): Response
    {
        $agents = Agent::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description'])
            ->map(fn (Agent $agent): array => [
                'id' => $agent->id,
                'name' => $agent->name,
                'slug' => $agent->slug,
                'description' => $agent->description,
            ])
            ->all();

        return Response::json(['agents' => $agents]);
    }
}

<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetPromptRunTool;
use App\Mcp\Tools\ListAgentsTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\RunAgentTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

/**
 * Lets a Claude Code session hand prompts to the application's agents.
 *
 * Given to a prompt run only when the agent's tools opt into it (an
 * `mcp__agents__*` entry), so the orchestrator (project-manager) can hand work
 * to the specialist agents, while ordinary agents — whose tools carry no such
 * entry — cannot keep starting further agent runs.
 */
#[Name('Developer Tool – Agentek')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Promptot küldhetsz a developer-tool agentjeinek. Egy prompt mindig egy
    projekten fut (`claude -p` a projekt könyvtárában, az agent system
    promptjával, modelljével és eszközeivel).

    Tipikus munkafolyamat:
    1. Hívd a `list-agents` és a `list-projects` eszközt a nevekért.
    2. A `run-agent` eszközzel sorba állítod a promptot; a válasz tartalmazza a
       futtatás azonosítóját (`run_id`). A prompt elhagyható — ekkor az agent a
       leírása alapján indul.
    3. A futás háttérben történik: a `get-prompt-run` eszközzel kérdezheted le
       az állapotát (pending, running, completed, failed) és a kimenetét.
    TXT)]
class AgentServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListAgentsTool::class,
        ListProjectsTool::class,
        RunAgentTool::class,
        GetPromptRunTool::class,
    ];
}

<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListAgentsTool;
use App\Mcp\Tools\ListMessagesTool;
use App\Mcp\Tools\SendMessageTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

/**
 * Lets agents working on the same project message each other. When the server is
 * started for a prompt run, the run's agent is the sender / reader and the run's
 * project is the conversation's scope; otherwise both must be named in the call.
 */
#[Name('Developer Tool – Üzenetek')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Üzenetküldés az ugyanazon a projekten dolgozó agentek között.

    - A `list-agents` eszközzel kérdezheted le, kinek írhatsz.
    - A `send-message` eszközzel üzenetet küldhetsz egy másik agentnek
      (a `to` paraméterben az agent neve, slug-ja vagy azonosítója).
    - A `list-messages` eszközzel olvashatod a neked érkezett üzeneteket.
      Alapértelmezetten csak az olvasatlanokat adja vissza, és a visszaadott
      üzeneteket olvasottnak jelöli.

    Agent-futás közben a küldő/olvasó agent és a projekt automatikusan a
    futásból jön. Futáson kívül add meg a `project` és az `agent` paramétert
    (melyik projektben, melyik agent nevében).
    TXT)]
class MessageServer extends Server
{
    /**
     * Environment variable holding the id of the prompt run the server was started for.
     */
    public const PROMPT_RUN_ENV = MailServer::PROMPT_RUN_ENV;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListAgentsTool::class,
        SendMessageTool::class,
        ListMessagesTool::class,
    ];
}

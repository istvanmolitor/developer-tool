<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ReplyEmailTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

/**
 * Lets an agent answer the e-mail it was handed. The server is started per
 * prompt run with the run's id in the environment, and only the messages
 * handed to that run can be answered.
 */
#[Name('Developer Tool – Levelezés')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Válasz küldése egy postafiókba érkezett levélre.

    Ha egy levelet kaptál feldolgozásra, a prompt megadja a levél azonosítóját
    (`incoming_email_id`). A `reply-email` eszközzel válaszolhatsz a feladónak:
    a válasz abból a postafiókból megy ki, amelybe a levél érkezett. Egy levélre
    csak egyszer válaszolhatsz, ezért előbb végezd el a teendőket.
    TXT)]
class MailServer extends Server
{
    /**
     * Environment variable holding the id of the prompt run the server was started for.
     */
    public const PROMPT_RUN_ENV = 'DEVELOPER_TOOL_PROMPT_RUN_ID';

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ReplyEmailTool::class,
    ];
}

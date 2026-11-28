<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ReplyEmailTool;
use App\Mcp\Tools\SendEmailTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

/**
 * Lets an agent answer the e-mail it was handed and send new mail from a mailbox
 * assigned to it. The server is started per prompt run with the run's id in the
 * environment; a reply only answers the message handed to that run, and new mail
 * only goes out from a mailbox the run's agent is assigned to.
 */
#[Name('Developer Tool – Levelezés')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Levelezés egy hozzád rendelt postafiókból.

    - `reply-email`: válasz egy postafiókba érkezett levélre. Ha egy levelet kaptál
      feldolgozásra, a prompt megadja a levél azonosítóját (`incoming_email_id`); a
      válasz abból a postafiókból megy ki, amelybe a levél érkezett. Egy levélre csak
      egyszer válaszolhatsz, ezért előbb végezd el a teendőket.
    - `send-email`: új levél küldése a hozzád rendelt postafiókból (nem válasz). Csak
      abból a postafiókból küldhetsz, amely hozzád van rendelve. Ha több is van, a
      `from` mezőben add meg, melyikből.

    A postafiók beállításától függően a levél azonnal kimegy, vagy piszkozatként
    jóváhagyásra vár.
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
        SendEmailTool::class,
    ];
}

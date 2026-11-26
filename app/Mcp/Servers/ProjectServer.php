<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\ListProjectDirectoriesTool;
use App\Mcp\Tools\ListProjectsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

/**
 * The projects of the developer-tool: the one place that lists them, and that
 * registers new ones. Every other server points here for project names instead
 * of carrying its own copy of the listing tool.
 *
 * Given to a prompt run only when the agent's tools opt into it (an
 * `mcp__projects__*` entry); listing and creating are separate tools, so an
 * agent allowed to list projects still cannot register one.
 */
#[Name('Developer Tool – Projektek')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    A developer-tool projektjei: itt kérdezheted le őket, és itt hozhatsz létre
    újat. Egy projekt a projektek könyvtárának egy mappája. A többi szerver (pl.
    `tasks`, `attachments`) a projektek nevét innen várja.

    Tipikus munkafolyamat:
    1. A `list-projects` eszközzel nézd meg, mely projektek léteznek már
       (a név egyedi).
    2. Meglévő mappa regisztrálásához hívd a `list-project-directories`
       eszközt, majd a `create-project` eszközt `mode: existing` móddal és a
       kiválasztott `directory` értékkel.
    3. Új üres mappához használd a `mode: new` módot és a `name` paramétert;
       repó klónozásához a `mode: git` módot és a `git_url` paramétert (a
       `name` elhagyható, ekkor a repó nevét kapja).
    4. A "new" és "git" projekt mappáját háttérfolyamat készíti elő: a válasz
       `provision_status` értéke ekkor `pending`. Az állapotot (`pending`,
       `provisioning`, `ready`, `failed`) és hiba esetén a `provision_error`
       okot később a `list-projects` eszköz kimenetében ellenőrizheted.
    5. A `git_url` csak https://, ssh:// vagy `user@host:útvonal` formátumú
       lehet.
    TXT)]
class ProjectServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListProjectsTool::class,
        ListProjectDirectoriesTool::class,
        CreateProjectTool::class,
    ];
}

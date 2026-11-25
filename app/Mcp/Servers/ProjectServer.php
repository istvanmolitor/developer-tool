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
 * Lets a Claude Code session create projects in the developer-tool.
 *
 * Deliberately not given to the prompt runs themselves, so an agent cannot
 * keep registering further projects.
 */
#[Name('Developer Tool – Projektek')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Projekteket hozhatsz létre a developer-tool alkalmazásban. Egy projekt a
    projektek könyvtárának egy mappája.

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

<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\SyncDocumentationTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

/**
 * Lets an agent push the Markdown documentation it wrote under a project's
 * `docs/documentations` folder into the documentations table, so the content
 * is stored and searchable in the application.
 *
 * Given to a prompt run only when the agent's tools opt into it (an
 * `mcp__docs__*` entry), so ordinary agents do not get it.
 */
#[Name('Developer Tool – Dokumentáció')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Betöltheted egy projekt dokumentációját az adatbázisba. A dokumentáció a
    projekt `docs/documentations` mappájában Markdown fájlként él; a betöltés
    útvonal szerint frissíti a sorokat, és eltávolítja a már törölt fájlok
    sorait, így a tábla mindig a mappát tükrözi.

    Tipikus munkafolyamat:
    1. Írd meg (vagy írasd meg) a dokumentáció Markdown fájljait a projekt
       `docs/documentations` mappájába.
    2. Hívd a `sync-documentation` eszközt a projekt nevével, hogy a tartalom
       az adatbázisba kerüljön és kereshető legyen.
    TXT)]
class DocumentationServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        SyncDocumentationTool::class,
    ];
}

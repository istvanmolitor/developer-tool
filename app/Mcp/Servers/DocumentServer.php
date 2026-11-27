<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\SyncDocumentsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

/**
 * Lets an agent push the Markdown files it wrote under a project's `docs`
 * folder into the documents table, so the content is stored and searchable in
 * the application.
 *
 * Given to a prompt run only when the agent's tools opt into it (an
 * `mcp__docs__*` entry), so ordinary agents do not get it.
 */
#[Name('Developer Tool – Dokumentumok')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Betöltheted egy projekt dokumentumait az adatbázisba. A dokumentumok a
    projekt `docs` mappájában élnek Markdown fájlként — az almappákban lévők
    is, például a `docs/documentations` mappa dokumentációja. A betöltés
    útvonal szerint frissíti a sorokat, és eltávolítja a már törölt fájlok
    sorait, így a tábla mindig a mappát tükrözi.

    Tipikus munkafolyamat:
    1. Írd meg (vagy írasd meg) a Markdown fájlokat a projekt `docs`
       mappájába (vagy annak egy almappájába).
    2. Hívd a `sync-documents` eszközt a projekt nevével, hogy a tartalom
       az adatbázisba kerüljön és kereshető legyen.
    TXT)]
class DocumentServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        SyncDocumentsTool::class,
    ];
}

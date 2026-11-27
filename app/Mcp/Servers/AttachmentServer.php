<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AttachToTaskTool;
use App\Mcp\Tools\CreateAttachmentTool;
use App\Mcp\Tools\DeleteAttachmentTool;
use App\Mcp\Tools\DetachFromTaskTool;
use App\Mcp\Tools\ListAttachmentsTool;
use App\Mcp\Tools\SyncAttachmentsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

/**
 * Lets an agent store and reference attached files. A file becomes an attachment
 * either by syncing the project's attachments folder (held centrally beside the
 * project directories, not inside the project), or by pointing at a file on disk;
 * attachments can then be bound to tasks so the agent can reference them later.
 * The attachment's stored absolute path is returned for that purpose.
 *
 * Given to a prompt run only when the agent's tools opt into it (an
 * `mcp__attachments__*` entry), so ordinary agents do not get it.
 */
#[Name('Developer Tool – Csatolmányok')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Csatolt fájlokat tárolhatsz és hivatkozhatsz rájuk. Minden csatolmány egy
    projekthez tartozik, és fizikailag a privát lemezre kerül; a válasz mindig
    tartalmazza a tárolt abszolút útvonalat (`path`), amelyre hivatkozni tudsz.

    Tipikus munkafolyamat:
    1. Mappa-alapú: tegyél fájlokat a projekt csatolmány-mappájába (a projekt-
       könyvtárak melletti közös `attachments` mappán belül, a projekt nevével),
       majd hívd a `sync-attachments` eszközt — a fájlok a lemezre másolódnak és
       az adatbázisba kerülnek (a törölt fájlok sorai eltűnnek).
    2. Egyedi fájl: a `create-attachment` eszközzel egy lemezen lévő fájlt
       csatolmányként elmentesz (visszakapod az azonosítót és az útvonalat).
    3. A `attach-to-task` eszközzel egy csatolmányt egy feladathoz kötsz, hogy a
       feladatnál hivatkozhass rá; a `detach-from-task` bontja a kötést.
    4. A `list-attachments` eszközzel egy projekt vagy egy feladat (task_id)
       csatolmányait listázod; a `delete-attachment` véglegesen töröl egyet.

    A csatolmányokra az azonosítójukkal (`attachment_id`), a projektekre a nevükkel
    (`project`), a feladatokra az azonosítójukkal (`task_id`) hivatkozz. A projektek
    nevét a `projects` szerver `list-projects` eszköze adja.
    TXT)]
class AttachmentServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        SyncAttachmentsTool::class,
        CreateAttachmentTool::class,
        ListAttachmentsTool::class,
        AttachToTaskTool::class,
        DetachFromTaskTool::class,
        DeleteAttachmentTool::class,
    ];
}

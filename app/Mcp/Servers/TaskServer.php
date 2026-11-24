<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\DeleteTaskTool;
use App\Mcp\Tools\ListAgentsTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\UpdateTaskTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Developer Tool – Feladatok')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
    Egyszerű feladatkezelő a developer-tool alkalmazáshoz. Minden feladat egy
    projekthez tartozik.

    Tipikus munkafolyamat:
    1. Hívd a `list-projects` eszközt a projektek nevének megismeréséhez.
    2. Hívd a `list-tasks` eszközt egy projekt feladatainak listázásához
       (opcionálisan szűrj státuszra: todo, in_progress, done).
    3. Új feladatot a `create-task`, módosítást a `update-task`, törlést a
       `delete-task` eszközzel végezhetsz.

    Egy feladat opcionálisan agenthez rendelhető. Az agentek neveit a
    `list-agents` eszközzel kérdezheted le; a `create-task` és `update-task`
    `agent` paraméterének az agent nevét (vagy azonosítóját) add meg. A
    hozzárendelést az `update-task` eszközben üres `agent` szöveggel törölheted.

    Státuszok: todo, in_progress, done. Prioritások: low, medium, high.
    A feladatokra az azonosítójukkal (`task_id`), a projektekre és agentekre a
    nevükkel (`project`, `agent`) hivatkozz.
    TXT)]
class TaskServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListProjectsTool::class,
        ListAgentsTool::class,
        ListTasksTool::class,
        CreateTaskTool::class,
        UpdateTaskTool::class,
        DeleteTaskTool::class,
    ];
}

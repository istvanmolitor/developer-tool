<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddTaskCommentTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\DeleteTaskTool;
use App\Mcp\Tools\ListTaskCommentsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\NextTaskTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Models\AgentContext;
use App\Models\PromptRun;
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
    1. Hívd a `list-tasks` eszközt egy projekt feladatainak listázásához
       (opcionálisan szűrj státuszra: todo, in_progress, done). A hozzád
       rendelt következő feladatot a `next-task` eszközzel kérdezheted le.
    2. Új feladatot a `create-task`, módosítást a `update-task`, törlést a
       `delete-task` eszközzel végezhetsz.
    3. Egy feladat hozzászólásait a `list-task-comments` eszközzel olvashatod,
       új hozzászólást (pl. haladás, kérdés, eredmény) az `add-task-comment`
       eszközzel írhatsz.

    Egy feladat opcionálisan agenthez rendelhető. A `create-task` és
    `update-task` `agent` paraméterének az agent nevét (vagy azonosítóját) add
    meg; a hozzárendelést az `update-task` eszközben üres `agent` szöveggel
    törölheted. A projektek és az agentek nevét a `projects` szerver
    `list-projects`, illetve az `agents` szerver `list-agents` eszköze adja.

    Státuszok: todo, in_progress, done. Prioritások: low, medium, high.
    A feladatokra az azonosítójukkal (`task_id`), a projektekre és agentekre a
    nevükkel (`project`, `agent`) hivatkozz.
    TXT)]
class TaskServer extends Server
{
    /**
     * Environment variable holding the id of the prompt run the server was started for.
     * Task changes made through the server are attributed to that run's agent.
     */
    public const PROMPT_RUN_ENV = MailServer::PROMPT_RUN_ENV;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListTasksTool::class,
        NextTaskTool::class,
        CreateTaskTool::class,
        UpdateTaskTool::class,
        DeleteTaskTool::class,
        ListTaskCommentsTool::class,
        AddTaskCommentTool::class,
    ];

    /**
     * The agent of the prompt run the server was started for, if any.
     */
    public static function currentAgentId(): ?int
    {
        return self::currentContext()?->agent_id;
    }

    /**
     * The project of the prompt run the server was started for, if any.
     */
    public static function currentProjectId(): ?int
    {
        return self::currentContext()?->project_id;
    }

    /**
     * The agent context of the prompt run the server was started for, if any.
     */
    private static function currentContext(): ?AgentContext
    {
        $promptRunId = (int) getenv(self::PROMPT_RUN_ENV);

        if ($promptRunId <= 0) {
            return null;
        }

        return PromptRun::query()->with('agentContext')->find($promptRunId)?->agentContext;
    }
}

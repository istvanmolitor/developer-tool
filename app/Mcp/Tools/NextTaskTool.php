<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\FormatsTasks;
use App\Mcp\Servers\TaskServer;
use App\Models\Agent;
use App\Models\Project;
use App\Repositories\Contracts\AgentRepository;
use App\Repositories\Contracts\ProjectRepository;
use App\Repositories\Contracts\TaskRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('next-task')]
#[Title('A következő feladatom')]
#[Description('Visszaadja a hozzád rendelt következő feladatot, amivel haladnod kell: a folyamatban lévő, majd a teendő feladatok közül a legmagasabb prioritásút. Agent-futásból hívva magától tudja, melyik agent és projekt futásáról van szó; azon kívül add meg az „agent” (és opcionálisan a „project”) paramétert.')]
#[IsReadOnly]
class NextTaskTool extends Tool
{
    use FormatsTasks;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'agent' => ['nullable', 'string'],
            'project' => ['nullable', 'string'],
        ]);

        $agent = isset($validated['agent'])
            ? $this->resolveAgent($validated['agent'])
            : $this->currentAgent();

        if ($agent === null) {
            return Response::error(
                isset($validated['agent'])
                    ? "Nincs „{$validated['agent']}” nevű agent. Használd a list-agents eszközt a nevekhez."
                    : 'Nem derült ki, melyik agent következő feladatát kéred. Add meg az „agent” paramétert, vagy futtasd az eszközt egy agent-futásból.',
            );
        }

        $project = isset($validated['project'])
            ? $this->resolveProject($validated['project'])
            : $this->currentProject();

        if (isset($validated['project']) && $project === null) {
            return Response::error("Nincs „{$validated['project']}” nevű projekt. Használd a list-projects eszközt a nevekhez.");
        }

        $tasks = app(TaskRepository::class)->openForAgent($agent, $project);

        $next = $tasks->first();

        if ($next === null) {
            return Response::json([
                'agent' => $agent->name,
                'project' => $project?->name,
                'next_task' => null,
                'remaining' => 0,
                'message' => 'Nincs több nyitott feladatod. Jó munka!',
            ]);
        }

        return Response::json([
            'agent' => $agent->name,
            'project' => $project?->name,
            'next_task' => $this->taskToArray($next),
            'remaining' => $tasks->count(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'agent' => $schema->string()
                ->description('Az agent neve (vagy azonosítója), akinek a következő feladatát kéred. Agent-futásból elhagyható — ekkor a futás agentje. A nevekhez használd a list-agents eszközt.'),
            'project' => $schema->string()
                ->description('Opcionális: csak ennek a projektnek a feladataira szűkít. Agent-futásból elhagyva a futás projektje. A nevekhez használd a list-projects eszközt.'),
        ];
    }

    /**
     * The agent of the current prompt run, if the server is bound to one.
     */
    private function currentAgent(): ?Agent
    {
        $id = TaskServer::currentAgentId();

        return $id !== null ? app(AgentRepository::class)->find($id) : null;
    }

    /**
     * The project of the current prompt run, if the server is bound to one.
     */
    private function currentProject(): ?Project
    {
        $id = TaskServer::currentProjectId();

        return $id !== null ? app(ProjectRepository::class)->find($id) : null;
    }
}

<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;

class TaskPromptBuilder
{
    /**
     * Build the prompt that hands the task to its agent. The prompt only names the
     * task (id + project) and tells the agent to read its full context through the
     * tasks MCP `get-task` tool — the task's title, description, history, change log
     * and attachments all come from there, so this prompt stays minimal.
     *
     * The closing paragraph is the runtime guard against hand-off loops: a run is
     * started automatically whenever a task is reassigned to another agent, so the
     * agent is reminded to hand off only with real next work, forward in the chain,
     * and to break out to the project manager instead of bouncing a task back and
     * forth. This reaches every auto-run uniformly without touching agent prompts.
     */
    public function build(Project $project, Task $task): string
    {
        return implode("\n", [
            "Dolgozz a(z) #{$task->id} azonosítójú feladaton a(z) „{$project->name}” projektben.",
            '',
            "Először olvasd be a feladat teljes kontextusát a `get-task` eszközzel (task_id: {$task->id}): "
                .'a leírást, a hozzászólás-előzményt, a változásnaplót és a csatolt fájlokat. Ebből derül ki, '
                .'mi a dolgod és mi vonatkozik rád. Ezután a system promptod szerint dolgozz a feladaton: '
                .'állítsd a státuszt és naplózd a munkád hozzászólásként.',
            '',
            'Átadás (fontos — az átadás automatikusan elindítja a következő agentet, ezért legyen tudatos):',
            '- Csak akkor add tovább másik agentnek, ha tényleg van értelmes következő lépése vele; '
                .'előre a láncban, a megfelelő szakértőnek. Ugyanannak az agentnek nem kell átadni — '
                .'ha a te részed még nem kész, maradjon nálad.',
            '- Nézd meg a változásnaplóban, járt-e már nálad ez a feladat. Ha érdemi, új tennivaló nélkül '
                .'kapnád vissza, NE pattintsd vissza reflexből annak, aki épp neked adta — az végtelen '
                .'oda-vissza kört csinál. Végezd el a konkrét részed, vagy ha megakadt/körbejár, állítsd '
                .'`done` státuszra és add vissza a `project-manager`-nek döntésre, a komment írja le, miért.',
            '- A lánc mindig véget ér: a kész munka `done` státusszal a `project-manager`-hez kerül '
                .'lezárásra — nem pörög tovább.',
        ]);
    }
}

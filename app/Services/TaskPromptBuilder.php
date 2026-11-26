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
     */
    public function build(Project $project, Task $task): string
    {
        return implode("\n", [
            "Dolgozz a(z) #{$task->id} azonosítójú feladaton a(z) „{$project->name}” projektben.",
            '',
            "Először olvasd be a feladat teljes kontextusát a `get-task` eszközzel (task_id: {$task->id}): "
                .'a leírást, a hozzászólás-előzményt, a változásnaplót és a csatolt fájlokat. Ebből derül ki, '
                .'mi a dolgod és mi vonatkozik rád. Ezután a system promptod szerint dolgozz a feladaton: '
                .'állítsd a státuszt, naplózd a munkád hozzászólásként, és add tovább a láncban.',
        ]);
    }
}

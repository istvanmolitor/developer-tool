<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;

class TaskPromptBuilder
{
    /**
     * Build the prompt that hands the task to its agent: the task's id, title,
     * type and priority, its description, and how to report progress through the
     * tasks MCP tools.
     */
    public function build(Project $project, Task $task): string
    {
        $lines = [
            "Dolgozz a(z) #{$task->id} azonosítójú feladaton a(z) „{$project->name}” projektben.",
            '',
            "Cím: {$task->title}",
            "Típus: {$task->type->label()}",
            "Prioritás: {$task->priority->label()}",
        ];

        if (filled($task->description)) {
            $lines[] = '';
            $lines[] = 'Leírás:';
            $lines[] = $task->description;
        }

        $lines[] = '';
        $lines[] = 'Amikor nekikezdesz, állítsd a feladat státuszát „folyamatban”-ra, '
            .'amikor pedig elkészültél, „kész”-re (update-task). A haladásodat és az '
            .'eredményt írd a feladathoz hozzászólásként (add-task-comment). A soron '
            .'következő feladatodat a next-task eszközzel kérdezheted le.';

        return implode("\n", $lines);
    }
}

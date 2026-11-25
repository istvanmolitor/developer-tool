<?php

namespace App\Console\Commands;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\ClaudePromptRunner;
use App\Services\TaskPromptBuilder;
use Illuminate\Console\Command;
use Throwable;

class RunAutoTasks extends Command
{
    protected $signature = 'tasks:run-auto';

    protected $description = 'Az automatikus végrehajtásra jelölt, agenthez rendelt teendő feladatok elindítása.';

    public function handle(ClaudePromptRunner $runner, TaskPromptBuilder $prompts): int
    {
        // Only tasks explicitly marked for auto-run, that have an agent to hand the
        // work to, and that are still waiting (todo). Setting each to in_progress
        // below keeps the next tick from picking the same task up again.
        $tasks = Task::query()
            ->where('auto_run', true)
            ->whereNotNull('agent_id')
            ->where('status', TaskStatus::Todo->value)
            ->with(['project', 'agent'])
            ->orderBy('position')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('Nincs elindítható automatikus feladat.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($tasks as $task) {
            // The project or agent may have been removed between the query and now.
            if ($task->project === null || $task->agent === null) {
                continue;
            }

            try {
                $runner->queueAgent($task->project, $task->agent, $prompts->build($task->project, $task));

                $task->update(['status' => TaskStatus::InProgress]);

                $this->info("#{$task->id} „{$task->title}” elindítva: {$task->agent->name}.");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("#{$task->id} „{$task->title}” elindítása nem sikerült: {$exception->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

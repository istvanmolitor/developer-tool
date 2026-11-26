<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\TaskRepository;
use App\Services\TaskService;
use Illuminate\Console\Command;
use Throwable;

class RunAutoTasks extends Command
{
    protected $signature = 'tasks:run-auto';

    protected $description = 'Az automatikus végrehajtásra jelölt, agenthez rendelt teendő feladatok elindítása.';

    public function handle(TaskService $tasks, TaskRepository $taskRepository): int
    {
        // Only tasks explicitly marked for auto-run, that have an agent to hand the
        // work to, and that are still waiting (todo). Setting each to in_progress
        // in TaskService::run keeps the next tick from picking the same task up again.
        $pending = $taskRepository->autoRunnable();

        if ($pending->isEmpty()) {
            $this->info('Nincs elindítható automatikus feladat.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($pending as $task) {
            // The project or agent may have been removed between the query and now.
            if ($task->project === null || $task->agent === null) {
                continue;
            }

            try {
                $tasks->run($task);

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

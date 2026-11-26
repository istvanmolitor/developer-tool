<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\PromptRun;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Contracts\TaskRepository;
use RuntimeException;

class TaskService
{
    public function __construct(
        private ClaudePromptRunner $runner,
        private TaskPromptBuilder $prompts,
        private TaskRepository $tasks,
    ) {}

    /**
     * Hand the task to its assigned agent: queue a prompt run that drives the work
     * and move the task to in_progress so the next tick won't pick it up again.
     * Returns the queued run.
     *
     * The optional user attributes the run to whoever triggered it (e.g. the
     * person who clicked play); auto-run leaves it null.
     *
     * @throws RuntimeException when the task has no project or no agent to run it.
     */
    public function run(Task $task, ?User $user = null): PromptRun
    {
        $project = $task->project;
        $agent = $task->agent;

        if ($project === null || $agent === null) {
            throw new RuntimeException("A(z) #{$task->id} feladatnak nincs projektje vagy hozzárendelt agentje.");
        }

        $run = $this->runner->queueAgent($project, $agent, $this->prompts->build($project, $task), $user);

        $this->tasks->update($task, ['status' => TaskStatus::InProgress]);

        return $run;
    }

    /**
     * Start the task automatically when it qualifies, otherwise do nothing.
     *
     * Called whenever a task is created or reassigned (see {@see \App\Observers\TaskObserver}):
     * a task flagged auto_run, with an agent to hand the work to, sitting in a
     * runnable status, is started right away instead of waiting for a tick.
     * Returns the queued run, or null when the task does not qualify.
     */
    public function autoRun(Task $task): ?PromptRun
    {
        if (! $task->auto_run || ! $task->status->isRunnable()) {
            return null;
        }

        // The agent (or project) may have been removed since; nothing to run.
        if ($task->project === null || $task->agent === null) {
            return null;
        }

        return $this->run($task);
    }
}

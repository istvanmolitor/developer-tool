<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\PromptRun;
use App\Models\Task;
use App\Models\User;
use RuntimeException;

class TaskService
{
    public function __construct(
        private ClaudePromptRunner $runner,
        private TaskPromptBuilder $prompts,
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

        $task->update(['status' => TaskStatus::InProgress]);

        return $run;
    }
}

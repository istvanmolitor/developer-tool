<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
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
     * Create a task under the given project.
     *
     * The single entry point for task creation: callers hand over the
     * validated attributes and the owning project, and the project binding is
     * applied here so no caller writes it directly on the model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(Project $project, array $attributes): Task
    {
        // project_id is guarded, so compose the task through the project
        // relation (which sets the foreign key) and let the repository persist.
        return $this->tasks->save($project->tasks()->make($attributes));
    }

    /**
     * Apply the attributes to an existing task and persist it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Task $task, array $attributes): Task
    {
        return $this->tasks->update($task, $attributes);
    }

    /**
     * Persist an already-composed (new or dirty) task.
     */
    public function save(Task $task): Task
    {
        return $this->tasks->save($task);
    }

    /**
     * Delete the given task.
     */
    public function delete(Task $task): void
    {
        $this->tasks->delete($task);
    }

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
}

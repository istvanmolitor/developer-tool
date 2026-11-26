<?php

namespace App\Observers;

use App\Enums\TaskStatus;
use App\Mcp\Servers\TaskServer;
use App\Models\Task;
use App\Models\TaskChange;
use App\Repositories\Contracts\SettingRepository;
use App\Services\TaskService;
use BackedEnum;
use Carbon\CarbonInterface;
use Throwable;

class TaskObserver
{
    public function __construct(
        private SettingRepository $settings,
        private TaskService $tasks,
    ) {}

    /**
     * When the "automatic task start" setting is on, every new task is created
     * with auto_run enabled so it is picked up and run without a manual start.
     */
    public function creating(Task $task): void
    {
        if ($this->settings->flag('auto_start_tasks')) {
            $task->auto_run = true;
        }
    }

    /**
     * A freshly created auto-run task is handed to its agent right away.
     */
    public function created(Task $task): void
    {
        $this->autoRun($task);
    }

    /**
     * Log the old and new value of every tracked field the save changed.
     *
     * The change is attributed to the logged-in user, or — when the save comes
     * from the tasks MCP server started for an agent's prompt run — to that agent.
     */
    public function updated(Task $task): void
    {
        // Capture these before any nested save (an auto-run moves the task to
        // in_progress) resets the dirty tracking the change log reads below.
        $agentChanged = $task->wasChanged('agent_id');
        $statusChanged = $task->wasChanged('status');
        $autoRunEnabled = $task->wasChanged('auto_run') && $task->auto_run;

        $changes = [];

        foreach (TaskChange::TRACKED_FIELDS as $field) {
            if (! $task->wasChanged($field)) {
                continue;
            }

            $old = $this->normalize($task->getOriginal($field));
            $new = $this->normalize($task->getAttribute($field));

            if ($old !== $new) {
                $changes[$field] = ['old' => $old, 'new' => $new];
            }
        }

        if ($changes !== []) {
            $userId = auth()->id();

            $task->taskChanges()->create([
                'user_id' => $userId,
                'agent_id' => $userId === null ? TaskServer::currentAgentId() : null,
                'changes' => $changes,
            ]);
        }

        // A meaningful change hands the (new) agent the task: a different
        // assignee, the task (re)entering the actionable "todo" state (promoted
        // from draft, reopened from done), or auto_run just switched on.
        //
        // Only becoming "todo" counts as a status trigger — never in_progress
        // (that is the run's own "work has started" signal, so triggering on it
        // would loop forever) nor done (a finished task has no new work).
        $becameTodo = $statusChanged && $task->status === TaskStatus::Todo;

        if ($agentChanged || $becameTodo || $autoRunEnabled) {
            $this->autoRun($task);
        }
    }

    /**
     * Hand the task to its agent when it qualifies for auto-run, swallowing any
     * start failure: a task that cannot be launched should still be saved.
     */
    private function autoRun(Task $task): void
    {
        try {
            $this->tasks->autoRun($task);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Reduce a cast attribute value to a plain, comparable scalar.
     */
    private function normalize(mixed $value): string|int|null
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof CarbonInterface => $value->toDateString(),
            $value === null, is_int($value) => $value,
            default => (string) $value,
        };
    }
}

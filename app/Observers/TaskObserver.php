<?php

namespace App\Observers;

use App\Mcp\Servers\TaskServer;
use App\Models\Task;
use App\Models\TaskChange;
use BackedEnum;
use Carbon\CarbonInterface;

class TaskObserver
{
    /**
     * Log the old and new value of every tracked field the save changed.
     *
     * The change is attributed to the logged-in user, or — when the save comes
     * from the tasks MCP server started for an agent's prompt run — to that agent.
     */
    public function updated(Task $task): void
    {
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

        if ($changes === []) {
            return;
        }

        $userId = auth()->id();

        $task->taskChanges()->create([
            'user_id' => $userId,
            'agent_id' => $userId === null ? TaskServer::currentAgentId() : null,
            'changes' => $changes,
        ]);
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

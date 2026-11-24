<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TaskChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of a task's change log: the old and new value of each tracked
 * field that changed in a single save, and who made the change.
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property int|null $agent_id
 * @property array<string, array{old: mixed, new: mixed}> $changes
 * @property CarbonImmutable|null $created_at
 * @property-read Task $task
 * @property-read User|null $user
 * @property-read Agent|null $agent
 */
class TaskChange extends Model
{
    /** @use HasFactory<TaskChangeFactory> */
    use HasFactory;

    /**
     * Log entries are written once and never edited.
     */
    public const UPDATED_AT = null;

    /**
     * Task fields whose changes are logged. Title and description are deliberately left out.
     *
     * @var list<string>
     */
    public const TRACKED_FIELDS = ['status', 'priority', 'type', 'agent_id', 'due_date'];

    protected $fillable = [
        'task_id',
        'user_id',
        'agent_id',
        'changes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * The user who made the change from the web UI, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The agent who made the change through the tasks MCP, if any.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}

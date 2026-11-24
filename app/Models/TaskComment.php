<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TaskCommentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A comment under a task, written by a user from the web UI or by an agent through the tasks MCP.
 *
 * @property int $id
 * @property int $task_id
 * @property int|null $user_id
 * @property int|null $agent_id
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Task $task
 * @property-read User|null $user
 * @property-read Agent|null $agent
 */
class TaskComment extends Model
{
    /** @use HasFactory<TaskCommentFactory> */
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'agent_id',
        'body',
    ];

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * The user who wrote the comment from the web UI, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The agent who wrote the comment through the tasks MCP, if any.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Display-ready shape for the frontend and MCP responses. The user and agent relations should be eager loaded.
     *
     * @return array{id: int, body: string, created_at: string|null, author: array{type: 'user'|'agent'|null, name: string|null}}
     */
    public function toDisplayArray(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            'author' => match (true) {
                $this->user_id !== null => ['type' => 'user', 'name' => $this->user?->name],
                $this->agent_id !== null => ['type' => 'agent', 'name' => $this->agent?->name],
                default => ['type' => null, 'name' => null],
            },
        ];
    }
}

<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Observers\TaskObserver;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $agent_id
 * @property string $title
 * @property TaskType $type
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property CarbonImmutable|null $due_date
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 * @property-read Agent|null $agent
 * @property-read Collection<int, TaskChange> $taskChanges
 * @property-read Collection<int, TaskComment> $comments
 */
#[ObservedBy(TaskObserver::class)]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'title',
        'type',
        'description',
        'status',
        'priority',
        'due_date',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'type' => TaskType::class,
            'due_date' => 'date',
        ];
    }

    /**
     * The project this task belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The agent assigned to this task, if any.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * The task's change log (status, priority, type, assignee, due date), newest first.
     *
     * @return HasMany<TaskChange, $this>
     */
    public function taskChanges(): HasMany
    {
        return $this->hasMany(TaskChange::class)->latest('id');
    }

    /**
     * Comments under the task written by users or agents, oldest first.
     *
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest('id');
    }
}

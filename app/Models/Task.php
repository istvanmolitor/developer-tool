<?php

namespace App\Models;

use App\Enums\SearchType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Observers\TaskObserver;
use App\Services\Search\IndexedInSearch;
use App\Services\Search\Searchable;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $agent_id
 * @property int|null $created_by_user_id
 * @property int|null $created_by_agent_id
 * @property string $title
 * @property TaskType $type
 * @property string|null $description
 * @property TaskStatus $status
 * @property bool $auto_run
 * @property TaskPriority $priority
 * @property CarbonImmutable|null $due_date
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 * @property-read Agent|null $agent
 * @property-read User|null $createdByUser
 * @property-read Agent|null $createdByAgent
 * @property-read array{type: 'user'|'agent'|null, name: string|null} $creator
 * @property-read Collection<int, TaskChange> $taskChanges
 * @property-read Collection<int, TaskComment> $comments
 */
#[ObservedBy(TaskObserver::class)]
class Task extends Model implements Searchable
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    use IndexedInSearch;

    protected $fillable = [
        'agent_id',
        'title',
        'type',
        'description',
        'status',
        'auto_run',
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
            'auto_run' => 'boolean',
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
     * The user who created the task from the web UI, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The agent who created the task through the tasks MCP, if any.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function createdByAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'created_by_agent_id');
    }

    /**
     * Display-ready creator: a user, an agent, or neither (legacy rows).
     * The createdByUser and createdByAgent relations should be eager loaded.
     *
     * @return Attribute<array{type: 'user'|'agent'|null, name: string|null}, never>
     */
    protected function creator(): Attribute
    {
        return Attribute::get(fn (): array => match (true) {
            $this->created_by_user_id !== null => ['type' => 'user', 'name' => $this->createdByUser?->name],
            $this->created_by_agent_id !== null => ['type' => 'agent', 'name' => $this->createdByAgent?->name],
            default => ['type' => null, 'name' => null],
        });
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

    /**
     * Files attached to this task, so an agent can reference them.
     *
     * @return BelongsToMany<Attachment, $this>
     */
    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class)->withTimestamps();
    }

    public static function searchType(): SearchType
    {
        return SearchType::Task;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchDocument(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->description,
            'project_id' => $this->project_id,
            'agent_ids' => $this->agent_id === null ? [] : [$this->agent_id],
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'priority_rank' => match ($this->priority) {
                TaskPriority::High => 0,
                TaskPriority::Medium => 1,
                default => 2,
            },
            'task_type' => $this->type->value,
            'position' => $this->position,
            'due_date' => $this->due_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

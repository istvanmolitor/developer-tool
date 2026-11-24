<?php

namespace App\Models;

use App\Enums\PromptRunStatus;
use App\Enums\SearchType;
use App\Services\Search\IndexedInSearch;
use App\Services\Search\Searchable;
use Database\Factories\PromptRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * @property PromptRunStatus $status
 */
class PromptRun extends Model implements Searchable
{
    /** @use HasFactory<PromptRunFactory> */
    use HasFactory;

    use IndexedInSearch;

    /**
     * A run is created once and streamed onto in place; there is no
     * "updated_at" column, so disable Eloquent's maintenance of it.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'project_id',
        'agent_id',
        'user_id',
        'prompt',
        'status',
        'exit_code',
        'output',
        'error_output',
        'cost_usd',
        'started_at',
        'finished_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PromptRunStatus::class,
            'exit_code' => 'integer',
            'cost_usd' => 'decimal:6',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * The project this prompt was run against.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The agent this prompt was run as, if any.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * The user who started this run, if known.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The incoming message this run was started to handle, if any.
     *
     * @return HasOne<IncomingEmail, $this>
     */
    public function incomingEmail(): HasOne
    {
        return $this->hasOne(IncomingEmail::class);
    }

    public static function searchType(): SearchType
    {
        return SearchType::Prompt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchDocument(): array
    {
        return [
            'title' => Str::limit(Str::squish($this->prompt), 150),
            'body' => $this->prompt,
            // The output can be huge; its beginning is plenty to find a run by.
            'extra' => Str::limit((string) $this->output, 20000, ''),
            'project_id' => $this->project_id,
            'agent_ids' => $this->agent_id === null ? [] : [$this->agent_id],
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

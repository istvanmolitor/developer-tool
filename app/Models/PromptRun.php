<?php

namespace App\Models;

use App\Enums\PromptRunStatus;
use Database\Factories\PromptRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PromptRun extends Model
{
    /** @use HasFactory<PromptRunFactory> */
    use HasFactory;

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
}

<?php

namespace App\Models;

use Database\Factories\AgentContextFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A `claude -p` conversation: the project it runs in, the agent it runs as and
 * the user who talks to it. Its runs continue the same claude session.
 */
class AgentContext extends Model
{
    /** @use HasFactory<AgentContextFactory> */
    use HasFactory;

    /**
     * A context only gets its session id filled in once; there is no
     * "updated_at" column, so disable Eloquent's maintenance of it.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'project_id',
        'agent_id',
        'user_id',
        'session_id',
    ];

    /**
     * Start a new context — a fresh claude session — for the given project, agent and user.
     */
    public static function start(Project $project, ?Agent $agent = null, ?User $user = null): self
    {
        return self::create([
            'project_id' => $project->id,
            'agent_id' => $agent?->id,
            'user_id' => $user?->id,
        ]);
    }

    /**
     * The context a chat of the given project, agent and user continues: their latest
     * context, or a new one when they have none yet.
     */
    public static function current(Project $project, ?Agent $agent, User $user): self
    {
        return self::query()
            ->where('project_id', $project->id)
            ->where('agent_id', $agent?->id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->first()
            ?? self::start($project, $agent, $user);
    }

    /**
     * Start a new context for the given user with this context's project and agent,
     * so the chat goes on in a fresh claude session.
     */
    public function startNew(User $user): self
    {
        return self::create([
            'project_id' => $this->project_id,
            'agent_id' => $this->agent_id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * The project the context's runs execute in.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The agent the context's runs execute as, if any.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * The user who started the context, if known.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The prompts run in this context.
     *
     * @return HasMany<PromptRun, $this>
     */
    public function promptRuns(): HasMany
    {
        return $this->hasMany(PromptRun::class);
    }
}

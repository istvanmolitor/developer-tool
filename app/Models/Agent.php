<?php

namespace App\Models;

use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'model',
        'tools',
        'system_prompt',
        'source_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tools' => 'array',
        ];
    }

    /**
     * The agent contexts (claude conversations) run as this agent.
     *
     * @return HasMany<AgentContext, $this>
     */
    public function agentContexts(): HasMany
    {
        return $this->hasMany(AgentContext::class);
    }

    /**
     * The prompt runs executed as this agent, through their contexts.
     *
     * @return HasManyThrough<PromptRun, AgentContext, $this>
     */
    public function promptRuns(): HasManyThrough
    {
        return $this->hasManyThrough(PromptRun::class, AgentContext::class);
    }

    /**
     * The tasks assigned to this agent.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * The mailboxes this agent sends and receives mail through.
     *
     * @return HasMany<Mailbox, $this>
     */
    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }

    /**
     * The messages this agent received from other agents.
     *
     * @return HasMany<AgentMessage, $this>
     */
    public function receivedMessages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'recipient_agent_id');
    }

    /**
     * The messages this agent sent to other agents.
     *
     * @return HasMany<AgentMessage, $this>
     */
    public function sentMessages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'sender_agent_id');
    }
}

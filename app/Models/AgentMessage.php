<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AgentMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message one agent sends to another agent within a project, through the messages MCP.
 *
 * @property int $id
 * @property int $project_id
 * @property int|null $sender_agent_id
 * @property int $recipient_agent_id
 * @property string $body
 * @property bool $is_read
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Project $project
 * @property-read Agent|null $sender
 * @property-read Agent $recipient
 */
class AgentMessage extends Model
{
    /** @use HasFactory<AgentMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'sender_agent_id',
        'recipient_agent_id',
        'body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The agent who wrote the message, if it still exists.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'sender_agent_id');
    }

    /**
     * The agent the message is addressed to.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'recipient_agent_id');
    }

    /**
     * @param  Builder<AgentMessage>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->where('is_read', false);
    }

    /**
     * Display-ready shape for MCP responses. The sender and recipient relations should be eager loaded.
     *
     * @return array{id: int, from: string|null, to: string, body: string, is_read: bool, read_at: string|null, created_at: string|null}
     */
    public function toDisplayArray(): array
    {
        return [
            'id' => $this->id,
            'from' => $this->sender?->name,
            'to' => $this->recipient->name,
            'body' => $this->body,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

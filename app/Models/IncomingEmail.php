<?php

namespace App\Models;

use App\Enums\SearchType;
use App\Observers\IncomingEmailObserver;
use App\Services\Search\IndexedInSearch;
use App\Services\Search\Searchable;
use Carbon\CarbonImmutable;
use Database\Factories\IncomingEmailFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $from_address
 * @property string|null $from_name
 * @property list<string>|null $to
 * @property list<string>|null $cc
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[ObservedBy(IncomingEmailObserver::class)]
class IncomingEmail extends Model implements Searchable
{
    /** @use HasFactory<IncomingEmailFactory> */
    use HasFactory;

    use IndexedInSearch;

    protected $fillable = [
        'mailbox_id',
        'uid',
        'message_id',
        'from_address',
        'from_name',
        'to',
        'cc',
        'subject',
        'body_text',
        'body_html',
        'received_at',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uid' => 'integer',
            'to' => 'array',
            'cc' => 'array',
            'received_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Remove the message's attachments (row and file) along with the message.
     * Each attachment's own deleting hook unlinks its file; the pivot rows
     * cascade in the database.
     */
    protected static function booted(): void
    {
        static::deleting(function (IncomingEmail $incomingEmail): void {
            $incomingEmail->attachments->each->delete();
        });
    }

    /**
     * @return BelongsTo<Mailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /**
     * Whether the mailbox's agent has read (received) the message.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * The prompt run in which the mailbox's agent read this message.
     *
     * @return BelongsTo<PromptRun, $this>
     */
    public function promptRun(): BelongsTo
    {
        return $this->belongsTo(PromptRun::class);
    }

    /**
     * The files attached to this message, stored on the central Attachment model.
     *
     * @return BelongsToMany<Attachment, $this>
     */
    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class)->withTimestamps();
    }

    /**
     * The agent's replies to this message.
     *
     * @return HasMany<OutgoingEmail, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(OutgoingEmail::class);
    }

    public static function searchType(): SearchType
    {
        return SearchType::Mail;
    }

    /**
     * @return Builder<static>
     */
    public static function searchableQuery(): Builder
    {
        return static::query()->with('mailbox:id,agent_id,project_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchDocument(): array
    {
        return [
            'title' => $this->subject ?: '(nincs tárgy)',
            'body' => $this->body_text,
            'extra' => trim($this->from_name.' '.$this->from_address),
            'direction' => 'incoming',
            'mailbox_id' => $this->mailbox_id,
            'project_id' => $this->mailbox?->project_id,
            'agent_ids' => $this->mailbox?->agent_id === null ? [] : [$this->mailbox->agent_id],
            'is_read' => $this->read_at !== null,
            'created_at' => $this->received_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

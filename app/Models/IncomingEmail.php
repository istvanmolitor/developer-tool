<?php

namespace App\Models;

use App\Enums\SearchType;
use App\Services\Search\IndexedInSearch;
use App\Services\Search\Searchable;
use Carbon\CarbonImmutable;
use Database\Factories\IncomingEmailFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
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
     * Remove the stored attachment files along with the message.
     */
    protected static function booted(): void
    {
        static::deleting(function (IncomingEmail $incomingEmail): void {
            Storage::disk('local')->deleteDirectory($incomingEmail->attachmentsDirectory());
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
     * The files attached to this message.
     *
     * @return HasMany<IncomingEmailAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(IncomingEmailAttachment::class);
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

    /**
     * Directory on the private `local` disk that holds this message's attachment files.
     */
    public function attachmentsDirectory(): string
    {
        return 'mail-attachments/'.$this->getKey();
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

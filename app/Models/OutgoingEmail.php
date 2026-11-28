<?php

namespace App\Models;

use App\Enums\OutgoingEmailStatus;
use App\Enums\SearchType;
use App\Observers\OutgoingEmailObserver;
use App\Services\Search\IndexedInSearch;
use App\Services\Search\Searchable;
use Carbon\CarbonImmutable;
use Database\Factories\OutgoingEmailFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A message written by a mailbox's agent, sent through the mailbox's SMTP account.
 *
 * @property list<string> $to
 * @property OutgoingEmailStatus $status
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[ObservedBy(OutgoingEmailObserver::class)]
class OutgoingEmail extends Model implements Searchable
{
    /** @use HasFactory<OutgoingEmailFactory> */
    use HasFactory;

    use IndexedInSearch;

    protected $fillable = [
        'mailbox_id',
        'incoming_email_id',
        'prompt_run_id',
        'to',
        'subject',
        'body_text',
        'in_reply_to',
        'status',
        'error',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'to' => 'array',
            'status' => OutgoingEmailStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Mailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /**
     * The message this one answers, if any.
     *
     * @return BelongsTo<IncomingEmail, $this>
     */
    public function incomingEmail(): BelongsTo
    {
        return $this->belongsTo(IncomingEmail::class);
    }

    /**
     * The agent run that wrote the message.
     *
     * @return BelongsTo<PromptRun, $this>
     */
    public function promptRun(): BelongsTo
    {
        return $this->belongsTo(PromptRun::class);
    }

    /**
     * The files sent with the message. These are project attachments, so they
     * stay with the project when the message is deleted (only the link is cut).
     *
     * @return BelongsToMany<Attachment, $this>
     */
    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class)->withTimestamps();
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
            'extra' => implode(' ', (array) $this->to),
            'direction' => 'outgoing',
            'status' => $this->status->value,
            'mailbox_id' => $this->mailbox_id,
            'incoming_email_id' => $this->incoming_email_id,
            'project_id' => $this->mailbox?->project_id,
            'agent_ids' => $this->mailbox?->agent_id === null ? [] : [$this->mailbox->agent_id],
            'created_at' => $this->sent_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

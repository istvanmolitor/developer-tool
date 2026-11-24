<?php

namespace App\Models;

use App\Enums\OutgoingEmailStatus;
use Database\Factories\OutgoingEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message written by a mailbox's agent, sent through the mailbox's SMTP account.
 *
 * @property list<string> $to
 * @property OutgoingEmailStatus $status
 */
class OutgoingEmail extends Model
{
    /** @use HasFactory<OutgoingEmailFactory> */
    use HasFactory;

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
}

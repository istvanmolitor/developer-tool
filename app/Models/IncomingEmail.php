<?php

namespace App\Models;

use Database\Factories\IncomingEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class IncomingEmail extends Model
{
    /** @use HasFactory<IncomingEmailFactory> */
    use HasFactory;

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
     * Whether the message has been opened in the app.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Mark the message as read, keeping the time it was first opened.
     */
    public function markAsRead(): void
    {
        if (! $this->isRead()) {
            $this->forceFill(['read_at' => now()])->save();
        }
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
     * Directory on the private `local` disk that holds this message's attachment files.
     */
    public function attachmentsDirectory(): string
    {
        return 'mail-attachments/'.$this->getKey();
    }
}

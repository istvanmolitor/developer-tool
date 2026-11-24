<?php

namespace App\Models;

use App\Enums\MailEncryption;
use App\Services\Search\SearchService;
use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property MailEncryption $imap_encryption
 * @property MailEncryption $smtp_encryption
 * @property int|null $agent_id
 * @property-read Agent|null $agent
 * @property int|null $project_id
 * @property-read Project|null $project
 * @property bool $auto_read
 * @property bool $auto_reply
 */
class Mailbox extends Model
{
    /** @use HasFactory<MailboxFactory> */
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'project_id',
        'name',
        'email',
        'from_name',
        'username',
        'password',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_folder',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'is_active',
        'auto_read',
        'auto_reply',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'imap_port' => 'integer',
            'imap_encryption' => MailEncryption::class,
            'smtp_port' => 'integer',
            'smtp_encryption' => MailEncryption::class,
            'is_active' => 'boolean',
            'auto_read' => 'boolean',
            'auto_reply' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_uid' => 'integer',
        ];
    }

    /**
     * The messages are removed by a database cascade, so their attachment files are cleaned up here.
     */
    protected static function booted(): void
    {
        static::deleting(function (Mailbox $mailbox): void {
            $mailbox->incomingEmails()->each(
                fn (IncomingEmail $incomingEmail) => Storage::disk('local')->deleteDirectory($incomingEmail->attachmentsDirectory()),
            );
        });

        // The cascaded mails leave the search index together.
        static::deleted(function (Mailbox $mailbox): void {
            app(SearchService::class)->removeWhere('mailbox_id', $mailbox->id);
        });
    }

    /**
     * The agent this mailbox belongs to, if any.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * The project the agent works on when it reads this mailbox's messages.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Whether an agent and a project are set, so the agent can read the messages.
     */
    public function canAgentRead(): bool
    {
        return $this->agent_id !== null && $this->project_id !== null;
    }

    /**
     * The messages downloaded from this mailbox.
     *
     * @return HasMany<IncomingEmail, $this>
     */
    public function incomingEmails(): HasMany
    {
        return $this->hasMany(IncomingEmail::class);
    }

    /**
     * The messages the agent wrote from this mailbox (replies and their drafts).
     *
     * @return HasMany<OutgoingEmail, $this>
     */
    public function outgoingEmails(): HasMany
    {
        return $this->hasMany(OutgoingEmail::class);
    }
}

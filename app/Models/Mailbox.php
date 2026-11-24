<?php

namespace App\Models;

use App\Enums\MailEncryption;
use Database\Factories\MailboxFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property MailEncryption $imap_encryption
 * @property MailEncryption $smtp_encryption
 */
class Mailbox extends Model
{
    /** @use HasFactory<MailboxFactory> */
    use HasFactory;

    protected $fillable = [
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
}

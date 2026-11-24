<?php

namespace App\Models;

use Database\Factories\IncomingEmailAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file attached to a downloaded message, stored on the private `local` disk.
 */
class IncomingEmailAttachment extends Model
{
    /** @use HasFactory<IncomingEmailAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'incoming_email_id',
        'filename',
        'mime_type',
        'size',
        'path',
    ];

    protected $hidden = [
        'path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<IncomingEmail, $this>
     */
    public function incomingEmail(): BelongsTo
    {
        return $this->belongsTo(IncomingEmail::class);
    }
}

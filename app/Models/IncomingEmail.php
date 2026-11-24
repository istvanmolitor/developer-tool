<?php

namespace App\Models;

use Database\Factories\IncomingEmailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
    }

    /**
     * @return BelongsTo<Mailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }
}

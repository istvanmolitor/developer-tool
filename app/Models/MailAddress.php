<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MailAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An e-mail address seen in incoming or outgoing mail. Every address is recorded
 * once (unique), can be named and flagged as spam, and gathers the conversation
 * of messages from and to it.
 *
 * @property int $id
 * @property string $mail
 * @property string|null $name
 * @property bool $is_spam
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class MailAddress extends Model
{
    /** @use HasFactory<MailAddressFactory> */
    use HasFactory;

    protected $fillable = [
        'mail',
        'name',
        'is_spam',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_spam' => 'boolean',
        ];
    }
}

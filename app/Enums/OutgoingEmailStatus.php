<?php

namespace App\Enums;

enum OutgoingEmailStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Failed = 'failed';

    /**
     * Whether the message may still be sent (a draft, or a retry after a failed send).
     */
    public function isSendable(): bool
    {
        return $this !== self::Sent;
    }
}

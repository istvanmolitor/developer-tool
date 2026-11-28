<?php

namespace App\Observers;

use App\Models\OutgoingEmail;
use App\Repositories\Contracts\MailAddressRepository;

/**
 * Records the recipients of every outgoing message, so each one is in the
 * mail_addresses table. The write happens inside the message's own transaction.
 */
class OutgoingEmailObserver
{
    public function __construct(private MailAddressRepository $mailAddresses) {}

    public function created(OutgoingEmail $email): void
    {
        foreach ($email->to ?? [] as $address) {
            $this->mailAddresses->remember($address);
        }
    }
}

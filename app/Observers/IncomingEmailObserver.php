<?php

namespace App\Observers;

use App\Models\IncomingEmail;
use App\Repositories\Contracts\MailAddressRepository;

/**
 * Records every address a received message carries — its sender, recipients and
 * copies — so each one is in the mail_addresses table. The write happens inside
 * the message's own transaction, so a rolled-back message records nothing.
 */
class IncomingEmailObserver
{
    public function __construct(private MailAddressRepository $mailAddresses) {}

    public function created(IncomingEmail $email): void
    {
        $this->mailAddresses->remember((string) $email->from_address, $email->from_name);

        foreach (array_merge($email->to ?? [], $email->cc ?? []) as $address) {
            $this->mailAddresses->remember($address);
        }
    }
}

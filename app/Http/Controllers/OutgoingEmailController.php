<?php

namespace App\Http\Controllers;

use App\Enums\OutgoingEmailStatus;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use App\Repositories\Contracts\OutgoingEmailRepository;
use App\Services\MailboxService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use RuntimeException;

/**
 * Approval of the replies the mailbox's agent wrote: send a draft (or retry a failed send), or discard it.
 */
class OutgoingEmailController extends Controller
{
    public function __construct(private OutgoingEmailRepository $outgoingEmails) {}

    /**
     * Send the draft reply through the mailbox's SMTP account.
     */
    public function send(Mailbox $mailbox, OutgoingEmail $outgoingEmail, MailboxService $mailboxes): RedirectResponse
    {
        try {
            $mailboxes->sendOutgoing($outgoingEmail);

            $toast = $outgoingEmail->status === OutgoingEmailStatus::Sent
                ? ['type' => 'success', 'message' => 'A válasz elküldve.']
                : ['type' => 'error', 'message' => "A küldés nem sikerült: {$outgoingEmail->error}"];
        } catch (RuntimeException $exception) {
            $toast = ['type' => 'error', 'message' => $exception->getMessage()];
        }

        Inertia::flash('toast', $toast);

        return redirect()->route('mailboxes.show', $mailbox);
    }

    /**
     * Discard a reply that has not been sent; a sent one stays as a record.
     */
    public function destroy(Mailbox $mailbox, OutgoingEmail $outgoingEmail): RedirectResponse
    {
        if (! $outgoingEmail->status->isSendable()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Elküldött válasz nem törölhető.']);

            return redirect()->route('mailboxes.show', $mailbox);
        }

        $this->outgoingEmails->delete($outgoingEmail);

        return redirect()
            ->route('mailboxes.show', $mailbox)
            ->with('status', 'Válasz elvetve.');
    }
}

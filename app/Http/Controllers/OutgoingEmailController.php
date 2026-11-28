<?php

namespace App\Http\Controllers;

use App\Enums\OutgoingEmailStatus;
use App\Http\Requests\StoreOutgoingEmailRequest;
use App\Models\Mailbox;
use App\Models\OutgoingEmail;
use App\Repositories\Contracts\OutgoingEmailRepository;
use App\Services\AttachmentService;
use App\Services\MailboxService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Composing new mail from a mailbox, and approval of the replies the mailbox's
 * agent wrote: send a draft (or retry a failed send), or discard it.
 */
class OutgoingEmailController extends Controller
{
    public function __construct(private OutgoingEmailRepository $outgoingEmails) {}

    /**
     * The compose screen: a blank message sent from this mailbox.
     */
    public function create(Mailbox $mailbox): Response
    {
        return Inertia::render('Mailboxes/Compose', [
            'mailbox' => $mailbox->only(['id', 'name', 'email', 'from_name']),
        ]);
    }

    /**
     * Send a new message from the mailbox, with any uploaded files attached. A
     * message written here goes out right away (a human approved it by sending it).
     */
    public function store(
        StoreOutgoingEmailRequest $request,
        Mailbox $mailbox,
        MailboxService $mailboxes,
        AttachmentService $attachments,
    ): RedirectResponse {
        $attachmentIds = [];

        foreach ($request->file('attachments', []) as $file) {
            $attachmentIds[] = $attachments
                ->storeUploadedFile($mailbox->project, $file, 'upload', $request->user())
                ->id;
        }

        $outgoing = $mailboxes->compose(
            $mailbox,
            $request->recipients(),
            $request->validated('subject'),
            $request->validated('body'),
            $attachmentIds,
            autoSend: true,
        );

        $toast = $outgoing->status === OutgoingEmailStatus::Sent
            ? ['type' => 'success', 'message' => 'A levél elküldve.']
            : ['type' => 'error', 'message' => "A küldés nem sikerült: {$outgoing->error}"];

        Inertia::flash('toast', $toast);

        // A composed message lands on the outgoing list where it now appears.
        return redirect()->route('mailboxes.outgoing', $mailbox);
    }

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

        return redirect()->route($this->listRoute($outgoingEmail), $mailbox);
    }

    /**
     * Discard a reply that has not been sent; a sent one stays as a record.
     */
    public function destroy(Mailbox $mailbox, OutgoingEmail $outgoingEmail): RedirectResponse
    {
        $route = $this->listRoute($outgoingEmail);

        if (! $outgoingEmail->status->isSendable()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Elküldött válasz nem törölhető.']);

            return redirect()->route($route, $mailbox);
        }

        $this->outgoingEmails->delete($outgoingEmail);

        return redirect()
            ->route($route, $mailbox)
            ->with('status', 'Válasz elvetve.');
    }

    /**
     * Where to return after acting on an outgoing message: a reply is managed from
     * the incoming list (under the message it answers), a standalone mail from the
     * outgoing list.
     */
    private function listRoute(OutgoingEmail $outgoingEmail): string
    {
        return $outgoingEmail->incoming_email_id !== null
            ? 'mailboxes.incoming'
            : 'mailboxes.outgoing';
    }
}

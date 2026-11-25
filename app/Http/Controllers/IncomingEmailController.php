<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\IncomingEmail;
use App\Models\Mailbox;
use App\Services\MailboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomingEmailController extends Controller
{
    /**
     * Have the mailbox's agent read the message: it receives the message as a prompt on the mailbox's project.
     */
    public function read(Request $request, Mailbox $mailbox, IncomingEmail $incomingEmail, MailboxService $mailboxes): RedirectResponse
    {
        try {
            $mailboxes->sendToAgent($incomingEmail, $request->user());

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Az agent megkapta a levelet.']);
        } catch (RuntimeException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);
        }

        return redirect()->route('mailboxes.show', $mailbox);
    }

    /**
     * Delete a downloaded message locally; the copy on the IMAP server is left untouched.
     */
    public function destroy(Mailbox $mailbox, IncomingEmail $incomingEmail): RedirectResponse
    {
        $incomingEmail->delete();

        return redirect()
            ->route('mailboxes.show', $mailbox)
            ->with('status', 'Levél törölve.');
    }

    /**
     * Download an attachment of a message. It is always sent as a download, never rendered
     * inline, so a sender-supplied HTML or SVG file cannot run in the app's origin.
     */
    public function downloadAttachment(Mailbox $mailbox, IncomingEmail $incomingEmail, Attachment $attachment): StreamedResponse
    {
        // The attachment must belong to this message.
        abort_unless($incomingEmail->attachments()->whereKey($attachment->getKey())->exists(), 404);

        return $attachment->toDownloadResponse();
    }
}

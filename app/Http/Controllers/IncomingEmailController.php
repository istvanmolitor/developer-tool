<?php

namespace App\Http\Controllers;

use App\Models\IncomingEmail;
use App\Models\IncomingEmailAttachment;
use App\Models\Mailbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IncomingEmailController extends Controller
{
    /**
     * Mark a downloaded message as read when it is opened.
     */
    public function markAsRead(Mailbox $mailbox, IncomingEmail $incomingEmail): RedirectResponse
    {
        $incomingEmail->markAsRead();

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
    public function downloadAttachment(Mailbox $mailbox, IncomingEmail $incomingEmail, IncomingEmailAttachment $attachment): StreamedResponse
    {
        $disk = Storage::disk('local');

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download($attachment->path, $attachment->filename, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}

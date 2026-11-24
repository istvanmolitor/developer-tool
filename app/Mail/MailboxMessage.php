<?php

namespace App\Mail;

use App\Models\Mailbox;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

/**
 * A free-form message sent from one of the stored mailboxes.
 */
class MailboxMessage extends Mailable
{
    public function __construct(
        public Mailbox $mailbox,
        public string $messageSubject,
        public string $messageBody,
        public bool $isHtml = false,
        public ?string $inReplyTo = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->mailbox->email, $this->mailbox->from_name),
            subject: $this->messageSubject,
        );
    }

    /**
     * A reply carries the answered Message-ID, so mail clients show it in the same thread.
     */
    public function headers(): Headers
    {
        if ($this->inReplyTo === null || $this->inReplyTo === '') {
            return new Headers;
        }

        return new Headers(
            references: [$this->inReplyTo],
            text: ['In-Reply-To' => Str::of($this->inReplyTo)->start('<')->finish('>')->value()],
        );
    }

    public function content(): Content
    {
        if ($this->isHtml) {
            return new Content(htmlString: $this->messageBody);
        }

        return new Content(
            text: 'mail.mailbox-text',
            with: ['body' => $this->messageBody],
        );
    }
}

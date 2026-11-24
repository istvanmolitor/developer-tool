<?php

namespace App\Mail;

use App\Models\Mailbox;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->mailbox->email, $this->mailbox->from_name),
            subject: $this->messageSubject,
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

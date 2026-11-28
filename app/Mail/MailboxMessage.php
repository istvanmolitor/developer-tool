<?php

namespace App\Mail;

use App\Models\Attachment;
use App\Models\Mailbox;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment as MailAttachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Str;

/**
 * A free-form message sent from one of the stored mailboxes.
 */
class MailboxMessage extends Mailable
{
    /**
     * @param  list<Attachment>  $files  The stored attachments to send with the message.
     */
    public function __construct(
        public Mailbox $mailbox,
        public string $messageSubject,
        public string $messageBody,
        public bool $isHtml = false,
        public ?string $inReplyTo = null,
        public array $files = [],
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

    /**
     * The stored files sent with the message, read from their absolute path.
     *
     * @return list<MailAttachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (Attachment $file): MailAttachment => MailAttachment::fromPath($file->absolutePath())
                ->as($file->filename)
                ->withMime($file->mime_type),
            $this->files,
        );
    }
}

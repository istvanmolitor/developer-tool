<?php

namespace App\Services\Mail;

use Carbon\CarbonImmutable;

/**
 * A single message downloaded from an IMAP folder, already decoded to UTF-8.
 */
final readonly class FetchedEmail
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<FetchedAttachment>  $attachments
     */
    public function __construct(
        public int $uid,
        public ?string $messageId,
        public ?string $fromAddress,
        public ?string $fromName,
        public array $to,
        public array $cc,
        public ?string $subject,
        public ?string $bodyText,
        public ?string $bodyHtml,
        public ?CarbonImmutable $receivedAt,
        public array $attachments = [],
    ) {}
}

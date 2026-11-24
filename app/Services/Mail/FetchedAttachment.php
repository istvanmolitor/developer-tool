<?php

namespace App\Services\Mail;

/**
 * A file attached to a downloaded message, with its content already decoded.
 */
final readonly class FetchedAttachment
{
    public function __construct(
        public string $filename,
        public string $mimeType,
        public string $content,
    ) {}
}

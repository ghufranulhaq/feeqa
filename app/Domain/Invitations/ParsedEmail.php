<?php

namespace App\Domain\Invitations;

/**
 * The fields `RawEmailParser` extracts before its caller discards the raw
 * message (FR-005-07). Nothing here retains the body text itself.
 */
final class ParsedEmail
{
    public function __construct(
        public readonly ?string $recipientEmail,
        public readonly ?string $recipientName,
        public readonly ?string $subject,
        public readonly ?string $fromAddress,
        public readonly bool $spfPass,
        public readonly bool $dkimPass,
        public readonly ?string $dkimDomain,
        public readonly ?string $spfMailFromDomain,
        public readonly string $searchableText,
    ) {}
}

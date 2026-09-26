<?php

namespace App\Actions\Reviews;

use App\Domain\Reviews\ReviewStatus;

/**
 * FR-003-11: the automated screening decision — always exactly one of
 * `published`, `held`, or `rejected`, with a reason recorded whenever the
 * author needs to see one (FR-003-12).
 */
final readonly class ScreeningOutcome
{
    public function __construct(
        public ReviewStatus $status,
        public ?string $reason = null,
    ) {}

    public static function published(): self
    {
        return new self(ReviewStatus::Published);
    }

    public static function held(string $reason): self
    {
        return new self(ReviewStatus::Held, $reason);
    }

    public static function rejected(string $reason): self
    {
        return new self(ReviewStatus::Rejected, $reason);
    }
}

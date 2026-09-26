<?php

namespace App\Domain\Verification;

/**
 * FR-004-06: always exactly `approved` or held for the T5 staff queue with
 * the specific reason recorded — mirrors App\Actions\Reviews\ScreeningOutcome's
 * shape for the same "one failure holds it, nothing is silently rejected" idea.
 */
final readonly class AutoApprovalOutcome
{
    private function __construct(
        public bool $approved,
        public ?string $reasonCode = null,
    ) {}

    public static function approved(): self
    {
        return new self(true);
    }

    public static function hold(string $reasonCode): self
    {
        return new self(false, $reasonCode);
    }
}

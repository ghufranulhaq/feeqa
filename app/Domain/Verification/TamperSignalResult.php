<?php

namespace App\Domain\Verification;

/**
 * FR-004-07: the outcome of one tamper check pass — whether the proof
 * looks genuine, and if not, which specific signal failed.
 */
final readonly class TamperSignalResult
{
    private function __construct(
        public bool $passed,
        public ?string $reasonCode = null,
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    public static function fail(string $reasonCode): self
    {
        return new self(false, $reasonCode);
    }
}

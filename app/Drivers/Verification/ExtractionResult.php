<?php

namespace App\Drivers\Verification;

use DateTimeInterface;

/**
 * What a receipt/document extraction attempt found (plan D12). Confidence
 * is 0-100; feature code (spec 004) decides its own approval threshold.
 */
final class ExtractionResult
{
    public function __construct(
        public readonly ?string $merchant,
        public readonly ?DateTimeInterface $date,
        public readonly ?string $reference,
        public readonly ?int $amountMinorUnits,
        public readonly ?string $currency,
        public readonly int $confidence,
    ) {}
}

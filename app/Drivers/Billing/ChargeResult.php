<?php

namespace App\Drivers\Billing;

final class ChargeResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $reference,
        public readonly ?string $cardLast4,
        public readonly ?string $failureReason = null,
    ) {}
}

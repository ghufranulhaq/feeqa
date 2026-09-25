<?php

namespace App\Drivers\Billing\Contracts;

use App\Drivers\Billing\ChargeResult;

/**
 * Charges a payment method (plan D15). Implementations must never store or
 * log more than the last 4 digits of $cardNumber (constitution §5.3, §5.4).
 */
interface BillingDriver
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function charge(int $amountMinorUnits, string $currency, string $cardNumber, array $context = []): ChargeResult;
}

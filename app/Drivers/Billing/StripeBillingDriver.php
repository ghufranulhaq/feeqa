<?php

namespace App\Drivers\Billing;

use App\Drivers\Billing\Contracts\BillingDriver;
use RuntimeException;

/**
 * BILLING_DRIVER=stripe (plan D15, production only). Laravel Cashier +
 * Stripe Tax. Built out when spec 017 (Plans & Billing) is implemented
 * (build order step 6) — the demo never contacts a real gateway.
 */
class StripeBillingDriver implements BillingDriver
{
    public function charge(int $amountMinorUnits, string $currency, string $cardNumber, array $context = []): ChargeResult
    {
        throw new RuntimeException('StripeBillingDriver is not implemented yet — see spec 017.');
    }
}

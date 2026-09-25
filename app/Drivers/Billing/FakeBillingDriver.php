<?php

namespace App\Drivers\Billing;

use App\Drivers\Billing\Contracts\BillingDriver;
use Illuminate\Support\Str;

/**
 * BILLING_DRIVER=fake (plan D15): every payment attempt succeeds, and no
 * gateway is contacted. Only the last 4 digits of whatever was entered are
 * kept (to show "Card ending 4242"); the rest is never stored or logged —
 * this method receives it but never writes it anywhere beyond the substr
 * below, so a real card number typed into the demo isn't kept.
 */
class FakeBillingDriver implements BillingDriver
{
    public function charge(int $amountMinorUnits, string $currency, string $cardNumber, array $context = []): ChargeResult
    {
        $digits = preg_replace('/\D/', '', $cardNumber) ?? '';
        $last4 = Str::substr($digits, -4) ?: '4242';

        return new ChargeResult(
            success: true,
            reference: 'FAKE-'.Str::upper(Str::random(10)),
            cardLast4: $last4,
        );
    }
}

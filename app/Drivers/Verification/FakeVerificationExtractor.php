<?php

namespace App\Drivers\Verification;

use App\Drivers\Verification\Contracts\VerificationExtractor;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * VERIFICATION_EXTRACTOR=fake (plan D12). Skips OCR and the LLM entirely
 * and returns plausible values that match the reviewed business: merchant
 * = the business name, a date within the experience window, a random
 * booking reference, and a sensible amount. Any uploaded file "verifies"
 * successfully — acceptable only under the demo conditions in constitution
 * §5.6 (password-protected site, fictional data), never in production
 * (enforced by App\Support\Environment::assertProductionIsSafe()).
 */
class FakeVerificationExtractor implements VerificationExtractor
{
    public function extract(string $filePath, array $context = []): ExtractionResult
    {
        $from = isset($context['experience_date_from'])
            ? CarbonImmutable::parse($context['experience_date_from'])
            : CarbonImmutable::now()->subDays(30);

        $to = isset($context['experience_date_to'])
            ? CarbonImmutable::parse($context['experience_date_to'])
            : CarbonImmutable::now();

        $date = $from->addSeconds(random_int(0, max(1, $to->diffInSeconds($from))));

        return new ExtractionResult(
            merchant: $context['business_name'] ?? 'Unknown Merchant',
            date: Carbon::instance($date),
            reference: 'BK'.random_int(100000, 999999),
            amountMinorUnits: random_int(1500, 45000),
            currency: $context['currency'] ?? 'GBP',
            confidence: 92,
        );
    }
}

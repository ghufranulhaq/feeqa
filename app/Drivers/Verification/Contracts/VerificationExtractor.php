<?php

namespace App\Drivers\Verification\Contracts;

use App\Drivers\Verification\ExtractionResult;

/**
 * Reads a proof upload (receipt, invoice, booking confirmation) for
 * Verified Experience (plan D12).
 */
interface VerificationExtractor
{
    /**
     * @param  array<string, mixed>  $context  e.g. ['business_name' => ..., 'experience_date_from' => ..., 'experience_date_to' => ...]
     */
    public function extract(string $filePath, array $context = []): ExtractionResult;
}

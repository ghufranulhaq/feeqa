<?php

namespace App\Domain\Verification;

/**
 * FR-004-09: "automatically mask anything that looks like a full card
 * number (PAN)." MVP limitation (T3 docs note): rather than attempt
 * pixel-level masking on an image, the system rejects storing a proof
 * whose extracted text contains what looks like a PAN outright — the
 * consumer can redact the image themselves and re-upload.
 */
final class ContainsPan
{
    public static function check(string $text): bool
    {
        preg_match_all('/(?:\d[ -]?){13,19}/', $text, $matches);

        foreach ($matches[0] as $match) {
            $digits = preg_replace('/\D/', '', $match) ?? '';

            if (strlen($digits) >= 13 && strlen($digits) <= 19 && self::passesLuhn($digits)) {
                return true;
            }
        }

        return false;
    }

    private static function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $alternate = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($alternate) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}

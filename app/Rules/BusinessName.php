<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Spec 002 edge cases table: reject a phone number or an ALL-CAPS name.
 * Deliberately does *not* reject a URL/domain-shaped name (unlike
 * App\Rules\DisplayName) — a business is routinely named after its own
 * domain (e.g. "Booking.com").
 *
 * Not an ImplicitRule on purpose (unlike DisplayName): "name" is only
 * conditionally required here (`required_without:domain`), which already
 * handles blank/absent on its own — Laravel skips a non-implicit rule
 * for a blank value the same way it does for an absent one, so this only
 * ever sees a real, non-empty candidate name to check the shape of.
 */
class BusinessName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $trimmed = trim($value);

        if (preg_match('/(\+?\d[\d\-\s().]{6,}\d)/', $trimmed)) {
            $fail('The :attribute cannot contain a phone number.');

            return;
        }

        if ($trimmed === mb_strtoupper($trimmed) && preg_match('/[A-Z]{2,}/', $trimmed)) {
            $fail('The :attribute cannot be written in all capitals.');
        }
    }
}

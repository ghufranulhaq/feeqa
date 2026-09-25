<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

/**
 * FR-001-05 (2-40 characters) and the spec 001 edge cases table: reject a
 * blank/whitespace-only name, one over 40 characters, one containing a URL,
 * email, or phone number, and one that impersonates a staff role.
 *
 * Implements the legacy Rule-based ImplicitRule contract (not the newer
 * ValidationRule) on purpose: Validator::presentOrRuleIsImplicit() only
 * treats a trimmed-empty value as validatable at all when the rule is
 * implicit in that sense, and only that interface marks a rule implicit in
 * this Laravel version.
 */
class DisplayName implements ImplicitRule
{
    /**
     * @var list<string>
     */
    private const STAFF_ROLES = ['moderator', 'senior moderator', 'mediator', 'support', 'admin'];

    private string $failureMessage = 'The :attribute is not a valid display name.';

    public function passes($attribute, $value): bool
    {
        if (! is_string($value)) {
            $this->failureMessage = 'The :attribute must be text.';

            return false;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            $this->failureMessage = 'The :attribute cannot be empty or only whitespace.';

            return false;
        }

        if (mb_strlen($trimmed) < 2 || mb_strlen($trimmed) > 40) {
            $this->failureMessage = 'The :attribute must be between 2 and 40 characters.';

            return false;
        }

        if (preg_match('/(https?:\/\/|www\.)\S+/i', $trimmed)
            || preg_match('/\b[a-z0-9-]+\.(com|net|org|io|co|uk|de|fr|es|app|dev)\b/i', $trimmed)) {
            $this->failureMessage = 'The :attribute cannot contain a URL.';

            return false;
        }

        if (preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+/', $trimmed)) {
            $this->failureMessage = 'The :attribute cannot contain an email address.';

            return false;
        }

        if (preg_match('/(\+?\d[\d\-\s().]{6,}\d)/', $trimmed)) {
            $this->failureMessage = 'The :attribute cannot contain a phone number.';

            return false;
        }

        $roleWords = implode('|', array_map(
            fn (string $role) => preg_quote($role, '/'),
            self::STAFF_ROLES,
        ));

        if (preg_match('/\b('.$roleWords.')\b/i', $trimmed)) {
            $this->failureMessage = 'The :attribute cannot impersonate a staff role.';

            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->failureMessage;
    }
}

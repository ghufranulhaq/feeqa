<?php

namespace App\Actions\Reviews;

use App\Support\Reviews\ReviewText;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * FR-003-02, FR-003-04: the field-level rules shared by submitting a
 * review (T3) and editing one (T10) — the same limits apply both times.
 */
class ReviewFieldGuards
{
    /**
     * FR-003-02, edge case: "Rating missing or outside 1-5".
     */
    public static function starRating(mixed $rating): int
    {
        if (! is_int($rating) || $rating < 1 || $rating > 5) {
            throw ValidationException::withMessages(['star_rating' => 'Choose a star rating from 1 to 5.']);
        }

        return $rating;
    }

    /**
     * FR-003-02, edge case: "Empty title or text, or only whitespace/emoji".
     */
    public static function title(string $raw): string
    {
        $title = ReviewText::sanitize($raw);
        $length = mb_strlen($title);

        if ($length < 5 || $length > 100) {
            throw ValidationException::withMessages(['title' => 'The title must be between 5 and 100 characters.']);
        }

        return $title;
    }

    /**
     * FR-003-02, edge case table: "Text > 5,000 characters... Emoji count
     * toward length, but text must have >= 30 non-whitespace characters."
     */
    public static function text(string $raw): string
    {
        $text = ReviewText::sanitize($raw);
        $length = mb_strlen($text);
        $nonWhitespaceLength = mb_strlen(preg_replace('/\s+/u', '', $text) ?? '');

        if ($length > 5000) {
            throw ValidationException::withMessages(['text' => 'The review text must be 5,000 characters or fewer.']);
        }

        if ($nonWhitespaceLength < 30) {
            throw ValidationException::withMessages(['text' => 'The review text must have at least 30 non-whitespace characters.']);
        }

        return $text;
    }

    /**
     * FR-003-20: a lifecycle update's text has its own bounds (20-2,000
     * characters), separate from a review's own (FR-003-02's 30-5,000).
     */
    public static function lifecycleUpdateText(string $raw): string
    {
        $text = ReviewText::sanitize($raw);
        $length = mb_strlen($text);

        if ($length < 20 || $length > 2000) {
            throw ValidationException::withMessages(['text' => 'The update text must be between 20 and 2,000 characters.']);
        }

        return $text;
    }

    /**
     * FR-003-04, edge case: "Date of experience in the future or > 12
     * months ago".
     */
    public static function dateOfExperience(string $raw): Carbon
    {
        try {
            $date = Carbon::parse($raw)->startOfDay();
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['date_of_experience' => 'Enter a valid date.']);
        }

        $today = Carbon::now()->startOfDay();

        if ($date->greaterThan($today)) {
            throw ValidationException::withMessages(['date_of_experience' => 'The date of experience cannot be in the future.']);
        }

        if ($date->lessThan($today->copy()->subMonths(12))) {
            throw ValidationException::withMessages(['date_of_experience' => 'The date of experience must be within the last 12 months.']);
        }

        return $date;
    }

    /**
     * FR-003-02: reference/order number, optional, <= 64 characters.
     */
    public static function referenceNumber(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (mb_strlen($value) > 64) {
            throw ValidationException::withMessages(['reference_number' => 'The reference number must be 64 characters or fewer.']);
        }

        return $value;
    }
}

<?php

namespace App\Actions\Reviews;

use App\Models\Business;
use App\Models\Review;
use App\Models\User;

/**
 * FR-003-11 through FR-003-13: rules-only automated screening. Not a
 * driver — specs/plan.md scopes the .env-switchable provider pattern
 * (constitution §5.1) to genuine external providers (AI, OCR, mail...);
 * word lists and text comparison need neither an API key nor a `fake`
 * variant for tests. Runs on every submission (T3) and every edit (T10).
 */
class ScreenReviewSubmission
{
    public function handle(User $reviewer, Business $business, string $title, string $text): ScreeningOutcome
    {
        if ($this->matchesBlocklist($title) || $this->matchesBlocklist($text)) {
            return ScreeningOutcome::rejected('Your review contains language that isn\'t allowed.');
        }

        if ($this->isNearIdenticalToAnotherBusiness($reviewer, $business, $text)) {
            return ScreeningOutcome::held(
                'Held for review: this text closely matches a review you posted about a different business.',
            );
        }

        return ScreeningOutcome::published();
    }

    private function matchesBlocklist(string $value): bool
    {
        $haystack = mb_strtolower($value);

        /** @var list<string> $blocklist */
        $blocklist = config('platform.reviews.screening.blocklist_words', []);

        foreach ($blocklist as $word) {
            $word = mb_strtolower(trim($word));

            if ($word !== '' && str_contains($haystack, $word)) {
                return true;
            }
        }

        return false;
    }

    private function isNearIdenticalToAnotherBusiness(User $reviewer, Business $business, string $text): bool
    {
        $normalized = $this->normalize($text);

        if ($normalized === '') {
            return false;
        }

        $windowDays = (int) config('platform.reviews.screening.near_identical_window_days', 30);
        $threshold = (int) config('platform.reviews.screening.near_identical_similarity_threshold', 90);

        $candidates = Review::query()
            ->where('reviewer_id', $reviewer->id)
            ->where('business_id', '!=', $business->id)
            ->where('created_at', '>=', now()->subDays($windowDays))
            ->pluck('text');

        foreach ($candidates as $candidate) {
            similar_text($normalized, $this->normalize($candidate), $percent);

            if ($percent >= $threshold) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($value)) ?? '');
    }
}

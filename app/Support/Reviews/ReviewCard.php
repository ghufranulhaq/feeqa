<?php

namespace App\Support\Reviews;

use App\Domain\Reviews\ReviewStatus;
use App\Models\CategoryQuestion;
use App\Models\Review;
use App\Models\ReviewLifecycleUpdate;

/**
 * FR-003-26: the fields a review card shows today. Reply, case summary,
 * and Verified Experience badge stay "coming soon" until specs 007, 010,
 * and 004 exist. Share/Report aren't owned by any task yet, so they still
 * aren't part of this shape.
 */
class ReviewCard
{
    /**
     * @return array{
     *     id: int, url: string,
     *     business: array{name: string, slug: string},
     *     author: array{name: string, avatar: ?string, country: ?string, published_reviews_count: int},
     *     star_rating: int, title: string, text: string,
     *     date_of_experience: string, published_at: ?string, edited_at: ?string, source_label: string,
     *     useful_count: int,
     *     current_rating: int, durability_signal: ?string,
     *     lifecycle_updates: list<array{milestone: string, star_rating: int, text: string, published_at: ?string}>,
     *     question_answers: list<array{label: string, type: string, value: mixed}>,
     * }
     */
    public static function present(Review $review): array
    {
        $reviewer = $review->reviewer;
        $business = $review->business;

        return [
            'id' => $review->id,
            'url' => route('businesses.reviews.show', [$business->slug, $review->id]),
            'business' => [
                'name' => $business->name,
                'slug' => $business->slug,
            ],
            'author' => [
                'name' => $reviewer->name,
                'avatar' => $reviewer->avatar,
                'country' => $reviewer->country,
                'published_reviews_count' => $reviewer->reviews()->published()->count(),
            ],
            'star_rating' => $review->star_rating,
            'title' => $review->title,
            'text' => $review->text,
            'date_of_experience' => $review->date_of_experience->toDateString(),
            'published_at' => $review->published_at?->toIso8601String(),
            // FR-003-23: "the card shows Edited with the date."
            'edited_at' => $review->edited_at?->toIso8601String(),
            'source_label' => $review->source_label->value,
            // T9's listing query eager-loads this via withCount() to avoid
            // an N+1 across a full page; a single-review view (no count
            // loaded) falls back to counting directly.
            'useful_count' => $review->useful_votes_count ?? $review->usefulVotes()->count(),
            // FR-003-21: "the current rating is the latest published
            // update's rating, or the original rating."
            'current_rating' => $review->currentRating(),
            'durability_signal' => $review->durability_signal?->value,
            'lifecycle_updates' => self::lifecycleUpdates($review),
            'question_answers' => self::questionAnswers($review),
        ];
    }

    /**
     * FR-003-21: "the review card must show the original and all updates
     * as a dated timeline" — published updates only, oldest first.
     *
     * @return list<array{milestone: string, star_rating: int, text: string, published_at: ?string}>
     */
    private static function lifecycleUpdates(Review $review): array
    {
        return $review->lifecycleUpdates()
            ->where('status', ReviewStatus::Published)
            ->orderBy('published_at')
            ->get()
            ->map(fn (ReviewLifecycleUpdate $update) => [
                'milestone' => $update->milestone->value,
                'star_rating' => $update->star_rating,
                'text' => $update->text,
                'published_at' => $update->published_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Resolves stored answers back to their question labels using the
     * question set version recorded at submission time (T4), not the
     * business's current effective questions — so a later category or
     * question-set change never rewrites what an old review displays.
     *
     * @return list<array{label: string, type: string, value: mixed}>
     */
    private static function questionAnswers(Review $review): array
    {
        if ($review->answers === null || $review->question_set_version === null) {
            return [];
        }

        $category = $review->business->primaryCategory;
        $questionSet = $category?->questionSets()->where('version', $review->question_set_version)->first();

        if ($questionSet === null) {
            return [];
        }

        return $questionSet->questions()->orderBy('order')->get()
            ->filter(fn (CategoryQuestion $question) => array_key_exists($question->key, $review->answers))
            ->map(fn (CategoryQuestion $question) => [
                'label' => $question->localisedLabel(),
                'type' => $question->type->value,
                'value' => $review->answers[$question->key],
            ])
            ->values()
            ->all();
    }
}

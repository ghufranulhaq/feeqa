<?php

namespace App\Support\Reviews;

use App\Models\CategoryQuestion;
use App\Models\Review;

/**
 * FR-003-26: the fields a review card shows today. Reply, case summary,
 * and Verified Experience badge stay "coming soon" until specs 007, 010,
 * and 004 exist. Useful count/Share/Report are spec 003 T8 and out of
 * scope for other specs, so they aren't part of this shape yet either.
 */
class ReviewCard
{
    /**
     * @return array{
     *     id: int, url: string,
     *     business: array{name: string, slug: string},
     *     author: array{name: string, avatar: ?string, country: ?string, published_reviews_count: int},
     *     star_rating: int, title: string, text: string,
     *     date_of_experience: string, published_at: ?string, source_label: string,
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
            'source_label' => $review->source_label->value,
            'question_answers' => self::questionAnswers($review),
        ];
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

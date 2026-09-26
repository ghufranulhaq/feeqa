<?php

namespace App\Support\Reviews;

use App\Domain\Reviews\SourceLabel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FR-003-28: parses and validates a profile review list's sort/filter
 * query parameters. `verified_experience`, `has_reply`, and `has_case` are
 * deliberately left out of validation — they name concepts specs 004, 007,
 * and 010 haven't built yet, so a query string carrying them is simply
 * ignored rather than rejected (Laravel's `validate()` only pulls the keys
 * it's told to check).
 */
class ReviewListFilters
{
    /**
     * @return array{
     *     sort: string,
     *     star_rating: list<int>,
     *     source_label: list<string>,
     *     has_update: bool,
     *     language: ?string,
     *     date_from: ?string,
     *     date_to: ?string,
     *     location: ?string,
     * }
     */
    public static function fromRequest(Request $request): array
    {
        $validated = $request->validate([
            'sort' => ['nullable', 'string', Rule::in(['recent', 'useful'])],
            'star_rating' => ['nullable', 'array'],
            'star_rating.*' => ['integer', 'between:1,5'],
            'source_label' => ['nullable', 'array'],
            'source_label.*' => [Rule::enum(SourceLabel::class)],
            'has_update' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string', 'max:10'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'location' => ['nullable', 'string'],
        ]);

        return [
            'sort' => $validated['sort'] ?? 'recent',
            'star_rating' => $validated['star_rating'] ?? [],
            'source_label' => $validated['source_label'] ?? [],
            'has_update' => $validated['has_update'] ?? false,
            'language' => $validated['language'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'location' => $validated['location'] ?? null,
        ];
    }
}

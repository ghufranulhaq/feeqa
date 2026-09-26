<?php

namespace App\Actions\Reviews;

use App\Domain\Reviews\ReviewStatus;
use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * FR-003-28, FR-003-29: sorts and filters a Business's (or one of its
 * Location's) published review list. Page size stays capped at 20 (T6's
 * choice, under FR-003-29's 50 maximum) — this task adds sort/filter, not
 * a page-size control.
 */
class ListReviewsForProfile
{
    private const PER_PAGE = 20;

    /**
     * @param  array{
     *     sort: string, star_rating: list<int>, source_label: list<string>,
     *     has_update: bool, language: ?string, date_from: ?string,
     *     date_to: ?string, location: ?string,
     * }  $filters
     */
    public function handle(Business $business, array $filters, ?Location $location = null): LengthAwarePaginator
    {
        if ($location === null && $filters['location'] !== null) {
            $location = $business->locations->firstWhere('slug', $filters['location']);
        }

        /** @var HasMany<Review, Business|Location> $reviews */
        $reviews = $location?->reviews() ?? $business->reviews();

        $query = $reviews->publiclyVisible()
            ->with('reviewer')
            ->withCount('usefulVotes');

        if ($filters['star_rating'] !== []) {
            $query->whereIn('star_rating', $filters['star_rating']);
        }

        if ($filters['source_label'] !== []) {
            $query->whereIn('source_label', $filters['source_label']);
        }

        if ($filters['has_update']) {
            $query->whereHas('lifecycleUpdates', fn (Builder $updates) => $updates->where('status', ReviewStatus::Published));
        }

        if ($filters['language'] !== null) {
            $query->where('language', $filters['language']);
        }

        if ($filters['date_from'] !== null) {
            $query->whereDate('date_of_experience', '>=', $filters['date_from']);
        }

        if ($filters['date_to'] !== null) {
            $query->whereDate('date_of_experience', '<=', $filters['date_to']);
        }

        match ($filters['sort']) {
            'useful' => $query->orderByDesc('useful_votes_count')->latest('published_at'),
            default => $query->latest('published_at'),
        };

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }
}

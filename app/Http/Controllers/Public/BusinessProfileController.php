<?php

namespace App\Http\Controllers\Public;

use App\Actions\Reviews\ListReviewsForProfile;
use App\Domain\Businesses\BusinessStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessSlugRedirect;
use App\Models\Review;
use App\Support\Reviews\ReviewCard;
use App\Support\Reviews\ReviewListFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BusinessProfileController extends Controller
{
    /**
     * FR-002-01, FR-002-02, FR-002-04, FR-002-05. Everything owned by a
     * later spec (scores, AI summary, replies, cases, similar businesses,
     * Consumer Warnings) is listed in `pending_features` instead of being
     * faked — the profile page shows those sections as "coming soon"
     * rather than pretending the data exists. Reviews (spec 003 T6, T9) are
     * real.
     */
    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        $business = Business::where('slug', $slug)->first();

        if ($business === null) {
            $redirect = BusinessSlugRedirect::where('old_slug', $slug)->first();

            if ($redirect === null) {
                throw new NotFoundHttpException;
            }

            return redirect()->to(route('businesses.show', $redirect->business->slug), 301);
        }

        $reviewFilters = ReviewListFilters::fromRequest($request);

        return Inertia::render('public/business-profile', [
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'logo_path' => $business->logo_path,
                'status' => $business->status->value,
                'is_claimed' => $business->status === BusinessStatus::Claimed,
                'is_closed' => $business->status === BusinessStatus::Closed,
                'claimed_at' => $business->claimed_at?->toDateString(),
                'description' => $business->description,
                'website' => $business->website,
                'email' => $business->email,
                'phone' => $business->phone,
                'address' => $business->address,
                'social_links' => $business->social_links,
                'country' => $business->country,
                'primary_category' => $business->primaryCategory
                    ? ['slug' => $business->primaryCategory->slug, 'name' => $business->primaryCategory->localisedName()]
                    : null,
                'secondary_categories' => $business->secondaryCategories
                    ->map(fn ($category) => ['slug' => $category->slug, 'name' => $category->localisedName()])
                    ->values(),
            ],
            // FR-002-16 scenario 4: a multi-location business links out to
            // each branch's own sub-page.
            'locations' => $business->locations->map(fn ($location) => [
                'slug' => $location->slug,
                'name' => $location->name,
                'city' => $location->address['city'] ?? null,
            ])->values(),
            // FR-002-27, FR-003-32: published reviews of other businesses
            // that tag this one, shown separately from the Business's own
            // reviews.
            'mentions' => $business->mentions()
                ->map(fn (Review $review) => ReviewCard::present($review))
                ->values(),
            // FR-003-26, FR-003-28, FR-003-29: real reviews, sorted,
            // filtered, and paginated (max page size 50 — 20 here, well
            // under the cap).
            'reviews' => (new ListReviewsForProfile)->handle($business, $reviewFilters)
                ->through(fn (Review $review) => ReviewCard::present($review)),
            'review_filters' => $reviewFilters,
            'pending_features' => [
                ['key' => 'review_score', 'label' => 'Review Score & Trust Index', 'spec' => '008'],
                ['key' => 'ai_summary', 'label' => 'AI summary', 'spec' => '011'],
                ['key' => 'reply_behaviour', 'label' => 'Reply-behaviour signals', 'spec' => '007'],
                ['key' => 'case_stats', 'label' => 'Case statistics', 'spec' => '010'],
                ['key' => 'similar_businesses', 'label' => 'Similar businesses', 'spec' => '009'],
                ['key' => 'consumer_warning', 'label' => 'Consumer Warning', 'spec' => '006'],
            ],
        ]);
    }
}

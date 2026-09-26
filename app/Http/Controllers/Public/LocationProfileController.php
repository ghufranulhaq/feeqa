<?php

namespace App\Http\Controllers\Public;

use App\Actions\Reviews\ListReviewsForProfile;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use App\Support\Reviews\ReviewCard;
use App\Support\Reviews\ReviewListFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocationProfileController extends Controller
{
    /**
     * FR-002-16: its own sub-page, own Review Score placeholder (spec
     * 008 — same "coming soon" pattern as the business profile, T3).
     * Reviews (spec 003 T9) are real, scoped to this Location only.
     */
    public function show(Request $request, string $businessSlug, string $locationSlug): Response
    {
        $business = Business::where('slug', $businessSlug)->first();

        if ($business === null) {
            throw new NotFoundHttpException;
        }

        $location = Location::where('business_id', $business->id)->where('slug', $locationSlug)->first();

        if ($location === null) {
            throw new NotFoundHttpException;
        }

        $reviewFilters = ReviewListFilters::fromRequest($request);

        return Inertia::render('public/location-profile', [
            'business' => ['name' => $business->name, 'slug' => $business->slug],
            'location' => [
                'slug' => $location->slug,
                'name' => $location->name,
                'address' => $location->address,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'phone' => $location->phone,
                'hours' => $location->hours,
            ],
            'reviews' => (new ListReviewsForProfile)->handle($business, $reviewFilters, $location)
                ->through(fn (Review $review) => ReviewCard::present($review)),
            'review_filters' => $reviewFilters,
            'pending_features' => [
                ['key' => 'review_score', 'label' => 'Review Score & Trust Index', 'spec' => '008'],
            ],
        ]);
    }
}

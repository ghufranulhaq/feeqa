<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Location;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LocationProfileController extends Controller
{
    /**
     * FR-002-16: its own sub-page, own Review Score placeholder (spec
     * 008 — same "coming soon" pattern as the business profile, T3).
     */
    public function show(string $businessSlug, string $locationSlug): Response
    {
        $business = Business::where('slug', $businessSlug)->first();

        if ($business === null) {
            throw new NotFoundHttpException;
        }

        $location = Location::where('business_id', $business->id)->where('slug', $locationSlug)->first();

        if ($location === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('public/location-profile', [
            'business' => ['name' => $business->name, 'slug' => $business->slug],
            'location' => [
                'name' => $location->name,
                'address' => $location->address,
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'phone' => $location->phone,
                'hours' => $location->hours,
            ],
            'pending_features' => [
                ['key' => 'review_score', 'label' => 'Review Score & Trust Index', 'spec' => '008'],
                ['key' => 'reviews', 'label' => 'Reviews', 'spec' => '003'],
            ],
        ]);
    }
}

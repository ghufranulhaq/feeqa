<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Review;
use App\Support\Reviews\ReviewCard;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * FR-003-29: "each review must have a permanent URL", independent of
 * whichever page (or page number) the business profile's review list is
 * showing.
 */
class ReviewController extends Controller
{
    public function show(string $slug, Review $review): Response
    {
        $business = Business::where('slug', $slug)->first();

        if ($business === null || $review->business_id !== $business->id || ! $review->isPubliclyVisible()) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('public/review', [
            'review' => ReviewCard::present($review),
        ]);
    }
}

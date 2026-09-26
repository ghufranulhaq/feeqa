<?php

namespace App\Http\Controllers\Public;

use App\Actions\Reviews\DeleteReview;
use App\Actions\Reviews\UpdateReview;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Review;
use App\Support\Reviews\ReviewCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * FR-003-29: "each review must have a permanent URL", independent of
 * whichever page (or page number) the business profile's review list is
 * showing. FR-003-23 through FR-003-25: the author (only) may edit or
 * delete it here.
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

    public function update(Request $request, Review $review, UpdateReview $action): RedirectResponse
    {
        $validated = $request->validate([
            'star_rating' => ['required', 'integer'],
            'title' => ['required', 'string'],
            'text' => ['required', 'string'],
            'date_of_experience' => ['required', 'date'],
            'reference_number' => ['nullable', 'string'],
            // FR-003-31, FR-003-33: whether it exists, isn't the reviewed
            // business, and isn't more than one tag is a business rule
            // enforced by the action (ReviewFieldGuards::taggedBusiness),
            // not here.
            'tagged_business_id' => ['nullable', 'integer'],
        ]);

        $taggedBusinessId = $validated['tagged_business_id'] ?? null;
        unset($validated['tagged_business_id']);

        $action->handle($request->user(), $review, [
            ...$validated,
            'tagged_business_ids' => $taggedBusinessId !== null ? [$taggedBusinessId] : [],
        ]);

        return back()->with('status', 'Review updated.');
    }

    public function destroy(Request $request, Review $review, DeleteReview $action): RedirectResponse
    {
        $action->handle($request->user(), $review);

        return back()->with('status', 'Review deleted.');
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Actions\Verification\MatchReviewReference;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * FR-004-13: the consumer-facing half of reference matching, paired with
 * SubmitTransactionRecords on the business side.
 */
class ReviewReferenceMatchController extends Controller
{
    public function store(Request $request, Review $review, MatchReviewReference $action): RedirectResponse
    {
        $validated = $request->validate(['reference' => ['required', 'string', 'max:255']]);

        $action->handle($request->user(), $review, $validated['reference']);

        return back()->with('status', 'Verification submitted.');
    }
}

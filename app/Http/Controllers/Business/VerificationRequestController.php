<?php

namespace App\Http\Controllers\Business;

use App\Actions\Businesses\RequestReviewVerification;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerificationRequestController extends Controller
{
    public function store(Request $request, Business $business, Review $review, RequestReviewVerification $action): RedirectResponse
    {
        $action->handle($business, $request->user(), $review);

        return back()->with('status', 'Verification requested.');
    }
}

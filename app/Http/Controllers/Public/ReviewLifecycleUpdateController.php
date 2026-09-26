<?php

namespace App\Http\Controllers\Public;

use App\Actions\Reviews\SubmitLifecycleUpdate;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * FR-003-17 through FR-003-22.
 */
class ReviewLifecycleUpdateController extends Controller
{
    public function store(Request $request, Review $review, SubmitLifecycleUpdate $action): RedirectResponse
    {
        $validated = $request->validate([
            'star_rating' => ['required', 'integer'],
            'text' => ['required', 'string'],
            'answers' => ['nullable', 'array'],
        ]);

        $action->handle($request->user(), $review, $validated);

        return back()->with('status', 'Update added.');
    }
}

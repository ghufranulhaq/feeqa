<?php

namespace App\Http\Controllers\Public;

use App\Actions\Reviews\RestoreReviewDraft;
use App\Actions\Reviews\SaveReviewDraft;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * FR-003-10. No review submission form exists yet — same "real endpoint,
 * not linked from anywhere" situation as spec 002's claim/location
 * endpoints. Device-local autosave (localStorage) needs no server route;
 * these two exist for the signed-in-user half of the requirement.
 */
class ReviewDraftController extends Controller
{
    public function store(Request $request, Business $business, SaveReviewDraft $action): array
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('business_id', $business->id)],
            'payload' => ['required', 'array'],
        ]);

        $location = isset($validated['location_id']) ? Location::find($validated['location_id']) : null;

        $draft = $action->handle($request->user(), $business, $location, $validated['payload']);

        return ['updated_at' => $draft->updated_at];
    }

    public function show(Request $request, Business $business, RestoreReviewDraft $action): array
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('business_id', $business->id)],
        ]);

        $location = isset($validated['location_id']) ? Location::find($validated['location_id']) : null;

        $draft = $action->handle($request->user(), $business, $location);

        return ['payload' => $draft?->payload];
    }
}

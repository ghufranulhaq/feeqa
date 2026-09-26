<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\ReviewBusinessProfileChangeRequest;
use App\Http\Controllers\Controller;
use App\Models\BusinessProfileChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * FR-002-07. No staff console UI reaches this yet (same situation as
 * spec 001's staff account creation) — these endpoints are real and
 * usable today, just not linked from anywhere.
 */
class BusinessProfileChangeRequestController extends Controller
{
    public function approve(Request $request, BusinessProfileChangeRequest $changeRequest, ReviewBusinessProfileChangeRequest $action): RedirectResponse
    {
        $action->handle($changeRequest, $request->user(), approve: true);

        return back()->with('status', 'Change approved.');
    }

    public function reject(Request $request, BusinessProfileChangeRequest $changeRequest, ReviewBusinessProfileChangeRequest $action): RedirectResponse
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $action->handle($changeRequest, $request->user(), approve: false, notes: $validated['notes'] ?? null);

        return back()->with('status', 'Change rejected.');
    }
}

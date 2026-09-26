<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\ReviewBusinessClaim;
use App\Http\Controllers\Controller;
use App\Models\BusinessClaim;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessClaimReviewController extends Controller
{
    public function approve(Request $request, BusinessClaim $claim, ReviewBusinessClaim $action): RedirectResponse
    {
        $action->handle($claim, $request->user(), approve: true);

        return back()->with('status', 'Claim approved.');
    }

    public function reject(Request $request, BusinessClaim $claim, ReviewBusinessClaim $action): RedirectResponse
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        $action->handle($claim, $request->user(), approve: false, notes: $validated['notes'] ?? null);

        return back()->with('status', 'Claim rejected.');
    }
}

<?php

namespace App\Http\Controllers\Staff;

use App\Actions\Staff\DecideVerificationProof;
use App\Domain\Verification\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\ReviewVerification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * FR-004-08: the minimal staff queue for pending verification proofs (T5),
 * same shape as UncategorisedBusinessesController's read-only JSON queue.
 */
class VerificationProofReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->user()->staffRole() === null) {
            throw new AuthorizationException('Only staff can see the verification proof queue.');
        }

        $verifications = ReviewVerification::query()
            ->where('status', VerificationStatus::Pending)
            ->with(['review.business', 'review.reviewer'])
            ->oldest()
            ->get(['id', 'review_id', 'method', 'reference_number', 'extracted_fields', 'confidence', 'created_at']);

        return response()->json(['verifications' => $verifications]);
    }

    public function approve(Request $request, ReviewVerification $verification, DecideVerificationProof $action): RedirectResponse
    {
        $validated = $request->validate(['reason_code' => ['nullable', 'string', 'max:255']]);

        $action->handle($verification, $request->user(), approve: true, reasonCode: $validated['reason_code'] ?? null);

        return back()->with('status', 'Verification proof approved.');
    }

    public function reject(Request $request, ReviewVerification $verification, DecideVerificationProof $action): RedirectResponse
    {
        $validated = $request->validate(['reason_code' => ['nullable', 'string', 'max:255']]);

        $action->handle($verification, $request->user(), approve: false, reasonCode: $validated['reason_code'] ?? null);

        return back()->with('status', 'Verification proof rejected.');
    }
}

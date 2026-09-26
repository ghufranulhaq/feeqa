<?php

namespace App\Http\Controllers\Public;

use App\Actions\Verification\RequestDocumentVerification;
use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * FR-004-02 through FR-004-11: "Verify this experience" — document upload.
 */
class ReviewVerificationController extends Controller
{
    public function store(Request $request, Review $review, RequestDocumentVerification $action): RedirectResponse
    {
        $validated = $request->validate([
            'proofs' => ['required', 'array', 'max:3'],
            'proofs.*' => ['file', 'max:10240'],
            'is_refund_or_cancellation' => ['nullable', 'boolean'],
        ]);

        $action->handle(
            $request->user(),
            $review,
            $request->file('proofs', []),
            (bool) ($validated['is_refund_or_cancellation'] ?? false),
        );

        return back()->with('status', 'Verification requested.');
    }
}

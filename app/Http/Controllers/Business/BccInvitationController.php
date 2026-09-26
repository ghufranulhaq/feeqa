<?php

namespace App\Http\Controllers\Business;

use App\Actions\Invitations\ImportBccEmail;
use App\Actions\Invitations\RotateBccAddress;
use App\Actions\Invitations\UpdateBccSettings;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-005-01 (bcc), FR-005-06, FR-005-07: the BCC method's own settings and
 * its demo `.eml` ingestion endpoint, kept separate from
 * `ReviewInvitationController` because these routes manage the Business's
 * own BCC configuration, not a `review_invitations` row directly.
 */
class BccInvitationController extends Controller
{
    public function importEmail(Request $request, Business $business, ImportBccEmail $action): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $result = $action->handle($business, $request->user(), $request->file('file'));

        return response()->json([
            'outcome' => $result->outcome->value,
            'invitation_id' => $result->invitation?->id,
        ]);
    }

    public function rotateAddress(Request $request, Business $business, RotateBccAddress $action): JsonResponse
    {
        $business = $action->handle($business, $request->user());

        return response()->json(['bcc_address' => $business->bcc_address]);
    }

    public function updateSettings(Request $request, Business $business, UpdateBccSettings $action): JsonResponse
    {
        $validated = $request->validate([
            'registered_senders' => ['nullable', 'array'],
            'registered_senders.*' => ['email'],
            'reference_pattern' => ['nullable', 'string', 'max:255'],
        ]);

        $business = $action->handle($business, $request->user(), $validated);

        return response()->json([
            'registered_senders' => $business->bcc_registered_senders,
            'reference_pattern' => $business->bcc_reference_pattern,
        ]);
    }
}

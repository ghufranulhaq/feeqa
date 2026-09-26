<?php

namespace App\Http\Controllers\Business;

use App\Actions\Invitations\CancelInvitation;
use App\Actions\Invitations\ImportInvitationsFromCsv;
use App\Actions\Invitations\RequestApiInvitation;
use App\Actions\Invitations\RequestManualInvitation;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ReviewInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * FR-005-01 (manual), FR-005-12: not to be confused with
 * `InvitationController`, which manages *team-member* invitations
 * (spec 001/002) — an unrelated, pre-existing concept that happens to
 * share the word "invitation".
 */
class ReviewInvitationController extends Controller
{
    public function store(Request $request, Business $business, RequestManualInvitation $action): JsonResponse
    {
        $validated = $request->validate([
            'recipient_email' => ['required', 'email'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:10'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $invitation = $action->handle($business, $request->user(), $validated);

        return response()->json([
            'id' => $invitation->id,
            'status' => $invitation->status->value,
            'scheduled_at' => $invitation->scheduled_at->toIso8601String(),
        ]);
    }

    public function storeApi(Request $request, Business $business, RequestApiInvitation $action): JsonResponse
    {
        $validated = $request->validate([
            'recipient_email' => ['required', 'email'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:10'],
            'reference' => ['required', 'string', 'max:255'],
            'product_skus' => ['nullable', 'array'],
            'product_skus.*' => ['string', 'max:100'],
            'travel_date' => ['nullable', 'date'],
            'send_at' => ['nullable', 'date'],
        ]);

        if (isset($validated['travel_date'])) {
            $validated['travel_date'] = Carbon::parse($validated['travel_date']);
        }

        if (isset($validated['send_at'])) {
            $validated['send_at'] = Carbon::parse($validated['send_at']);
        }

        $invitation = $action->handle($business, $request->user(), $validated);

        return response()->json([
            'id' => $invitation->id,
            'status' => $invitation->status->value,
            'scheduled_at' => $invitation->scheduled_at->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, Business $business, ReviewInvitation $reviewInvitation, CancelInvitation $action): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $action->handle($reviewInvitation, $request->user(), $validated['reason'] ?? null);

        return response()->json(['status' => $reviewInvitation->fresh()->status->value]);
    }

    public function importCsv(Request $request, Business $business, ImportInvitationsFromCsv $action): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        $result = $action->handle($business, $request->user(), $request->file('file'));

        return response()->json([
            'created' => $result->created->count(),
            'collapsed' => $result->collapsed,
            'errors' => $result->errors,
        ]);
    }
}

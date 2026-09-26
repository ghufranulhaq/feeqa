<?php

namespace App\Http\Controllers\Business;

use App\Actions\Invitations\ComputeInvitationAnalytics;
use App\Domain\Businesses\BusinessPermission;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-005-19: "Invitation analytics ... must be available to Business
 * users with the Analyst role or above." `ViewAnalytics` is already
 * exactly that set — Analyst is the only role holding just this one
 * permission (BusinessRole::permissions()).
 */
class InvitationAnalyticsController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function index(Request $request, Business $business, ComputeInvitationAnalytics $analytics): JsonResponse
    {
        if (! $business->userCan($request->user(), BusinessPermission::ViewAnalytics)) {
            throw new AuthorizationException('You cannot view invitation analytics for this business.');
        }

        return response()->json($analytics->handle($business));
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Actions\Invitations\RecordInvitationClick;
use App\Actions\Invitations\RecordInvitationOpen;
use App\Actions\Invitations\ResolveInvitationToken;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * FR-005-10, FR-005-11: the two links every sent invitation email
 * contains. No review-submission page reads the resolved data yet (this
 * spec's own opening note) — `show` returns it as JSON, the same
 * honest-placeholder shape as every other unbuilt-frontend endpoint in
 * this codebase.
 */
class ReviewInvitationLinkController extends Controller
{
    public function show(string $token, ResolveInvitationToken $resolve, RecordInvitationClick $recordClick): JsonResponse
    {
        $result = $resolve->handle($token);

        if ($result->expired) {
            return response()->json([
                'expired' => true,
                'message' => 'This invitation expired. Write an Organic review instead.',
            ]);
        }

        $recordClick->handle($result->invitation);

        return response()->json([
            'expired' => false,
            'business_id' => $result->invitation->business_id,
            'location_id' => $result->invitation->location_id,
            'product_skus' => $result->invitation->product_skus,
            'reference' => $result->invitation->reference,
            'recipient_name' => $result->invitation->recipient_name,
            'locale' => $result->invitation->locale,
        ]);
    }

    public function pixel(string $token, ResolveInvitationToken $resolve, RecordInvitationOpen $recordOpen): Response
    {
        try {
            $recordOpen->handle($resolve->handle($token)->invitation);
        } catch (ModelNotFoundException) {
            // An unknown token still gets a pixel back — no signal to a
            // prying client either way.
        }

        // A 1x1 transparent GIF, the smallest valid image response.
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7');

        return response($pixel, 200, ['Content-Type' => 'image/gif']);
    }
}

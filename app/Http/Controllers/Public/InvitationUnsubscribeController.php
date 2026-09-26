<?php

namespace App\Http\Controllers\Public;

use App\Actions\Invitations\Unsubscribe;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FR-005-15: one click, no sign-in — deliberately outside every `auth`
 * group. The token alone (already in the recipient's own email footer) is
 * both the identity check and the authorization.
 */
class InvitationUnsubscribeController extends Controller
{
    public function show(Request $request, string $token, Unsubscribe $action): JsonResponse
    {
        $validated = $request->validate([
            'global' => ['nullable', 'boolean'],
        ]);

        $action->handle($token, (bool) ($validated['global'] ?? false));

        return response()->json(['status' => 'unsubscribed']);
    }
}

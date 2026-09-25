<?php

namespace App\Http\Controllers\Business;

use App\Actions\Businesses\AcceptBusinessInvitation;
use App\Http\Controllers\Controller;
use App\Models\BusinessInvitation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessInvitationAcceptController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $invitation = BusinessInvitation::where('token', $token)->firstOrFail();
        $invitation->load(['business', 'inviter']);

        return Inertia::render('business/accept-invitation', [
            'invitation' => [
                'token' => $invitation->token,
                'business_name' => $invitation->business->name,
                'role' => $invitation->businessRole()->name,
                'inviter_name' => $invitation->inviter->name,
                'is_for_current_user' => strtolower($request->user()->email) === $invitation->email,
                'already_accepted' => $invitation->accepted_at !== null,
                'expired' => $invitation->expires_at->isPast(),
            ],
        ]);
    }

    public function accept(Request $request, string $token, AcceptBusinessInvitation $action): RedirectResponse
    {
        $invitation = BusinessInvitation::where('token', $token)->firstOrFail();

        try {
            $action->handle($invitation, $request->user());
        } catch (AuthorizationException $e) {
            return back()->withErrors(['invitation' => $e->getMessage()]);
        }

        return to_route('dashboard')->with('status', "You've joined {$invitation->business->name}.");
    }
}

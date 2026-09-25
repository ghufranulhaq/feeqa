<?php

namespace App\Http\Controllers\Business;

use App\Actions\Businesses\InviteBusinessMember;
use App\Domain\Businesses\BusinessRole;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function store(Request $request, Business $business, InviteBusinessMember $action): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', Rule::enum(BusinessRole::class)],
        ]);

        $action->handle(
            $business,
            $request->user(),
            $validated['email'],
            BusinessRole::from($validated['role']),
        );

        return back()->with('status', 'Invitation sent.');
    }
}

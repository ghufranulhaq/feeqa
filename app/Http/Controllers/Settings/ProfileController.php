<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Notifications\AccountDeletionScheduledNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'countries' => config('countries'),
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * FR-001-20: request account deletion. Public content is hidden right
     * away (BlockPendingDeletionAccounts middleware, ReviewerProfileController);
     * personal data is erased/pseudonymised within 30 days
     * (ErasePendingAccountDeletions, scheduled daily).
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        // A passwordless/social-only account has no password to confirm —
        // being authenticated as them is confirmation enough.
        if ($user->password !== null) {
            $request->validate(['password' => ['required', 'current_password']]);
        }

        $user->forceFill(['deletion_requested_at' => now()])->save();
        $user->notify(new AccountDeletionScheduledNotification);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}

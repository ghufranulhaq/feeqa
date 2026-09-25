<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\DisplayName;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared final step for any sign-up path that verifies an email/provider
 * address before a local account exists — passwordless (T8) and social
 * (T9). Session key `pending_signup_email` marks a verified-but-not-yet-an-
 * account email; this controller is the only place that clears it.
 */
class CompleteProfileController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $email = $request->session()->get('pending_signup_email');

        if (! $email) {
            return redirect()->route('login');
        }

        return Inertia::render('auth/complete-profile', [
            'email' => $email,
            'countries' => config('countries'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $email = $request->session()->get('pending_signup_email');

        if (! $email) {
            return redirect()->route('login');
        }

        $request->validate([
            'name' => [new DisplayName],
            'country' => ['required', 'string', 'size:2', Rule::in(array_keys(config('countries')))],
            'over_18' => ['required', 'accepted'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $email,
            'country' => $request->country,
            'date_of_birth_confirmed_at' => now(),
        ]);

        // Not mass-assignable on purpose (email_verified_at is not in
        // User::$fillable) — set explicitly, since we already proved
        // control of this inbox via the passwordless code/link.
        $user->forceFill(['email_verified_at' => now()])->save();

        $user->consents()->create([
            'terms_version' => config('legal.terms_version'),
            'privacy_version' => config('legal.privacy_version'),
            'marketing_opt_in' => false,
            'consented_at' => now(),
        ]);

        $request->session()->forget('pending_signup_email');

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}

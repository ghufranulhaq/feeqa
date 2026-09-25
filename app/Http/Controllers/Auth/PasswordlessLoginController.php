<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordlessCode;
use App\Models\User;
use App\Notifications\PasswordlessSignInNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FR-001-01: passwordless email sign-in/sign-up (scenario 1: "Continue with
 * email"). Works for both an existing account (signs them in) and a brand
 * new email (hands off to CompleteProfileController to collect the display
 * name, country, and 18+ confirmation before the account is created).
 */
class PasswordlessLoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/passwordless-request');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $email = strtolower($request->string('email')->toString());
        $code = PasswordlessCode::issueFor($email);

        Notification::route('mail', $email)->notify(new PasswordlessSignInNotification($code));

        return redirect()->route('login.passwordless.verify', ['email' => $email]);
    }

    public function showVerify(Request $request): Response
    {
        return Inertia::render('auth/passwordless-verify', [
            'email' => $request->query('email', ''),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'code' => ['required', 'string'],
        ]);

        $match = PasswordlessCode::usable()
            ->where('email', strtolower($request->string('email')->toString()))
            ->where('code', $request->string('code')->toString())
            ->first();

        if (! $match) {
            return back()->withErrors(['code' => 'That code is invalid or has expired.'])->withInput();
        }

        return $this->signInOrHandOff($match);
    }

    public function viaToken(string $token): RedirectResponse
    {
        $match = PasswordlessCode::usable()->where('token', $token)->first();

        if (! $match) {
            return redirect()->route('login')->withErrors(['email' => 'That link is invalid or has expired.']);
        }

        return $this->signInOrHandOff($match);
    }

    private function signInOrHandOff(PasswordlessCode $code): RedirectResponse
    {
        $code->consume();

        $user = User::where('email', $code->email)->first();

        if (! $user) {
            session([
                'pending_signup_email' => $code->email,
                // Proven by demonstrating control of the inbox (code/link).
                'pending_signup_email_verified' => true,
            ]);

            return redirect()->route('signup.complete');
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}

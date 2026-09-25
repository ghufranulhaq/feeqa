<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SignInInsteadNotification;
use App\Rules\DisplayName;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            // DisplayName is implicit: it rejects a blank/whitespace-only
            // value itself, so no separate 'required' is needed here.
            'name' => [new DisplayName],
            // No 'unique' rule here on purpose (edge case: a duplicate email
            // must not surface as a validation error — that's exactly the
            // kind of response an automated tool can scan to enumerate
            // which emails have accounts). Checked manually below instead.
            'email' => 'required|string|lowercase|email|max:255',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            // FR-001-03: blocked here means nothing is ever written for an
            // under-18 visitor — the email is never stored.
            'over_18' => ['required', 'accepted'],
        ]);

        if ($existing = User::where('email', $request->email)->first()) {
            $existing->notify(new SignInInsteadNotification);

            return redirect()->route('login')
                ->with('status', 'Check your email to continue.');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'date_of_birth_confirmed_at' => now(),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return to_route('dashboard');
    }
}

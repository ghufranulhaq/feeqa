<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\AbstractUser as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * FR-001-01, FR-001-02, FR-001-06 (plan D6): Google, Apple, Facebook. Buttons
 * only appear when a provider's client_id is configured (available()), and
 * the routes reject any other provider name the same way regardless.
 */
class SocialLoginController extends Controller
{
    /**
     * @return list<string>
     */
    public static function available(): array
    {
        return collect(['google', 'apple', 'facebook'])
            ->filter(fn (string $provider) => filled(config("services.{$provider}.client_id")))
            ->values()
            ->all();
    }

    public function redirect(string $provider): SymfonyRedirectResponse
    {
        $this->ensureSupported($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        $this->ensureSupported($provider);

        /** @var SocialiteUser $socialiteUser */
        $socialiteUser = Socialite::driver($provider)->user();

        $link = UserProvider::where('provider', $provider)
            ->where('provider_user_id', $socialiteUser->getId())
            ->first();

        if ($link) {
            /** @var User $linkedUser */
            $linkedUser = $link->user;

            Auth::login($linkedUser);

            return redirect()->intended(route('dashboard', absolute: false));
        }

        $email = $socialiteUser->getEmail();
        $verified = $this->emailIsVerified($provider, $socialiteUser);

        $existing = $email ? User::where('email', $email)->first() : null;

        if ($existing) {
            if (! $verified) {
                // Edge case: an unverified provider email must not be able
                // to attach itself to somebody else's existing account.
                return redirect()->route('login')->withErrors([
                    'email' => "We couldn't confirm your email with this provider. Please sign in another way.",
                ]);
            }

            $existing->providers()->create([
                'provider' => $provider,
                'provider_user_id' => $socialiteUser->getId(),
            ]);

            Auth::login($existing);

            return redirect()->intended(route('dashboard', absolute: false));
        }

        session([
            'pending_signup_email' => $email,
            // FR-001-02, edge case: unverified provider email still needs
            // the normal email-verification step before publishing.
            'pending_signup_email_verified' => $verified,
            'pending_signup_provider' => [
                'provider' => $provider,
                'provider_user_id' => $socialiteUser->getId(),
            ],
        ]);

        return redirect()->route('signup.complete');
    }

    private function ensureSupported(string $provider): void
    {
        if (! in_array($provider, self::available(), true)) {
            throw new NotFoundHttpException;
        }
    }

    private function emailIsVerified(string $provider, SocialiteUser $socialiteUser): bool
    {
        $raw = $socialiteUser->getRaw();

        return match ($provider) {
            'google', 'apple' => filter_var($raw['email_verified'] ?? true, FILTER_VALIDATE_BOOLEAN),
            // Facebook Login only ever returns a confirmed email address.
            'facebook' => true,
            default => false,
        };
    }
}

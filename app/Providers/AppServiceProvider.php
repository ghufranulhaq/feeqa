<?php

namespace App\Providers;

use App\Support\Environment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Apple\Provider as AppleProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // FR-001-04: every Password::defaults() call (registration, password
        // reset, password settings) gets the environment-driven minimum
        // length and breached-password check from here, in one place.
        Password::defaults(function () {
            $rule = Password::min(Environment::passwordMinLength());

            return Environment::checkBreachedPasswords()
                ? $rule->uncompromised()
                : $rule;
        });

        // D6: Socialite's built-in Google/Facebook drivers need no listener;
        // Apple isn't built in, so socialiteproviders/apple adds itself here.
        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('apple', AppleProvider::class);
        });
    }
}

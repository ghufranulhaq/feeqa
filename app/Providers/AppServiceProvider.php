<?php

namespace App\Providers;

use App\Support\Environment;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
    }
}

<?php

namespace App\Providers;

use App\Support\Environment;
use Illuminate\Support\ServiceProvider;

class PlatformServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Environment::assertProductionIsSafe();
    }
}

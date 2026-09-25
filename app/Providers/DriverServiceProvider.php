<?php

namespace App\Providers;

use App\Drivers\Ai\Contracts\AiDriver;
use App\Drivers\Ai\FakeAiDriver;
use App\Drivers\Ai\OpenAiCompatibleAiDriver;
use App\Drivers\Billing\Contracts\BillingDriver;
use App\Drivers\Billing\FakeBillingDriver;
use App\Drivers\Billing\StripeBillingDriver;
use App\Drivers\Malware\ClamAvMalwareScanner;
use App\Drivers\Malware\Contracts\MalwareScanner;
use App\Drivers\Malware\FakeMalwareScanner;
use App\Drivers\Signing\SigningService;
use App\Drivers\Transcription\Contracts\TranscriptionDriver;
use App\Drivers\Transcription\FakeTranscriptionDriver;
use App\Drivers\Transcription\UnconfiguredTranscriptionDriver;
use App\Drivers\Verification\Contracts\VerificationExtractor;
use App\Drivers\Verification\FakeVerificationExtractor;
use App\Drivers\Verification\OcrLlmVerificationExtractor;
use Illuminate\Support\ServiceProvider;

/**
 * Binds every .env-selected provider (constitution §5.1, plan D11-D16) to
 * its contract. Each area defaults to its `fake` implementation; switching
 * providers is a .env change only (App\Support\Environment refuses to boot
 * in production with any of these still set to `fake`).
 */
class DriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiDriver::class, fn () => match (config('platform.ai.driver')) {
            'fake' => new FakeAiDriver,
            default => new OpenAiCompatibleAiDriver,
        });

        $this->app->singleton(VerificationExtractor::class, fn () => match (config('platform.verification.extractor')) {
            'fake' => new FakeVerificationExtractor,
            default => new OcrLlmVerificationExtractor,
        });

        $this->app->singleton(TranscriptionDriver::class, fn () => match (config('platform.transcription.driver')) {
            'fake' => new FakeTranscriptionDriver,
            default => new UnconfiguredTranscriptionDriver,
        });

        $this->app->singleton(MalwareScanner::class, fn () => match (config('platform.malware.scanner')) {
            'fake' => new FakeMalwareScanner,
            default => new ClamAvMalwareScanner,
        });

        $this->app->singleton(BillingDriver::class, fn () => match (config('platform.billing.driver')) {
            'fake' => new FakeBillingDriver,
            default => new StripeBillingDriver,
        });

        $this->app->singleton(SigningService::class, fn () => new SigningService(
            keysPath: config('platform.signing.keys_path'),
            activeKid: config('platform.signing.active_kid'),
        ));
    }
}

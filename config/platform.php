<?php

use App\Actions\Accounts\Export\AccountDataCollector;

// Every external provider is selected here, from .env only (constitution
// §5.1). Each has a `fake` driver. Password rules and staff IP allow-listing
// are NOT read from .env — see App\Support\Environment — so production can't
// be relaxed by a stray environment variable (plan D6, D26).

return [

    /*
    |--------------------------------------------------------------------------
    | AI (constitution §5.1, plan D11)
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'driver' => env('AI_DRIVER', 'fake'),
        'base_url' => env('AI_BASE_URL'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL'),
        'model_fast' => env('AI_MODEL_FAST'),
        'timeout' => (int) env('AI_TIMEOUT', 60),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 4000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Receipt / document reading for Verified Experience (plan D12)
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'extractor' => env('VERIFICATION_EXTRACTOR', 'fake'),
        'ocr_languages' => env('OCR_LANGUAGES', 'eng'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voice/video transcripts (plan D12a)
    |--------------------------------------------------------------------------
    */
    'transcription' => [
        'driver' => env('TRANSCRIPTION_DRIVER', 'fake'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Malware scanning (plan D13)
    |--------------------------------------------------------------------------
    */
    'malware' => [
        'scanner' => env('MALWARE_SCANNER', 'fake'),
        'clamav_host' => env('CLAMAV_HOST'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments (plan D15)
    |--------------------------------------------------------------------------
    */
    'billing' => [
        'driver' => env('BILLING_DRIVER', 'fake'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Staff console (plan D7). Allow-list is only enforced in production —
    | see App\Support\Environment::staffIpAllowlistEnforced().
    |--------------------------------------------------------------------------
    */
    'staff' => [
        'allowed_ips' => array_filter(explode(',', (string) env('STAFF_ALLOWED_IPS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Signed attestations (plan D16) — sodium Ed25519, JWS format
    |--------------------------------------------------------------------------
    */
    'signing' => [
        'keys_path' => env('SIGNING_KEYS_PATH', storage_path('app/signing/keys.json')),
        'active_kid' => env('SIGNING_ACTIVE_KID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account security (spec 001, plan D6)
    |--------------------------------------------------------------------------
    | check_breached_passwords can be switched off for offline development
    | (it calls the public "Have I Been Pwned" k-anonymity API). Production
    | always checks regardless of this value — see Environment::checkBreachedPasswords().
    */
    'security' => [
        'check_breached_passwords' => env('PASSWORD_CHECK_BREACHED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data export (FR-001-19)
    |--------------------------------------------------------------------------
    | Every App\Contracts\ExportsUserData implementation listed here
    | contributes a section to a user's export. Add to this list, don't
    | change BuildDataExport, when a new spec adds personal data.
    */
    'export' => [
        'collectors' => [
            AccountDataCollector::class,
        ],
        'download_link_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales (constitution §5.5, FR-001-05, FR-001-08)
    |--------------------------------------------------------------------------
    | Launch locale is en-GB only. Adding a locale is a config change, not a
    | code change — the architecture must not need code changes to add one.
    */
    'locales' => [
        'default' => 'en-GB',
        'supported' => ['en-GB'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Business listing checks (FR-002-10)
    |--------------------------------------------------------------------------
    | Not one of constitution §5.1's named external providers (no vendor to
    | swap), so it isn't covered by Environment::assertProductionIsSafe() —
    | but it still defaults to `fake` locally/demo, `dns` in production.
    */
    'business_listing' => [
        'checker' => env('BUSINESS_LISTING_CHECKER', 'fake'),
        'blocklist_domains' => array_filter(explode(',', env('BUSINESS_LISTING_BLOCKLIST_DOMAINS', ''))),
        'blocklist_keywords' => array_filter(explode(',', env(
            'BUSINESS_LISTING_BLOCKLIST_KEYWORDS',
            'porn,xxx,casino,escort',
        ))),
    ],

];

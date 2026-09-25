<?php

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

];

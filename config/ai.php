<?php

// Trimmed from the laravel/ai package's published default: the Platform
// uses a single provider entry ("openai-compatible"), switched entirely by
// AI_BASE_URL/AI_API_KEY/AI_MODEL in .env (constitution §5.1, plan D11).
// Changing provider or model needs no code change — only .env. The
// OpenAiCompatibleAiDriver in app/Drivers/Ai wraps this SDK and is what
// application code actually depends on.

return [

    'default' => 'openai-compatible',

    'providers' => [
        'openai-compatible' => [
            'driver' => 'openai-compatible',
            'url' => env('AI_BASE_URL'),
            'key' => env('AI_API_KEY'),
        ],
    ],

];

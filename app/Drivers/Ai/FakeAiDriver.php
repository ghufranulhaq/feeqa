<?php

namespace App\Drivers\Ai;

use App\Drivers\Ai\Contracts\AiDriver;
use RuntimeException;

/**
 * AI_DRIVER=fake (plan D11). Deliberately does not simulate a real AI
 * response: callers must check isAvailable() and fall back to their own
 * feature-specific behaviour (e.g. keyword-matched topics, a pre-written
 * summary from the demo seeders, a generic reply template) — that fallback
 * logic belongs to each feature, not to this driver.
 */
class FakeAiDriver implements AiDriver
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function complete(string $prompt, array $options = []): AiResponse
    {
        throw new RuntimeException(
            'AI is disabled (AI_DRIVER=fake). Check isAvailable() before calling complete(), and use the feature\'s fallback instead.'
        );
    }
}

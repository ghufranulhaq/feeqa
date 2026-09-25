<?php

namespace App\Drivers\Ai;

use App\Drivers\Ai\Contracts\AiDriver;
use Laravel\Ai\AnonymousAgent;
use RuntimeException;

/**
 * AI_DRIVER=openai-compatible (plan D11): wraps the laravel/ai SDK's
 * "openai-compatible" provider, whose URL/key/model are switched entirely
 * by AI_BASE_URL/AI_API_KEY/AI_MODEL in .env — the demo points these at the
 * DeepSeek API, production at an EU host, with no code change either way.
 */
class OpenAiCompatibleAiDriver implements AiDriver
{
    public function isAvailable(): bool
    {
        return filled(config('platform.ai.base_url')) && filled(config('platform.ai.api_key'));
    }

    public function complete(string $prompt, array $options = []): AiResponse
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('AI provider is not configured: set AI_BASE_URL and AI_API_KEY.');
        }

        $model = $options['model'] ?? config('platform.ai.model');

        $agent = new AnonymousAgent(
            instructions: $options['instructions'] ?? 'You are a helpful assistant for the feeqa review platform.',
            messages: [],
            tools: [],
        );

        $response = $agent->prompt(
            $prompt,
            provider: 'openai-compatible',
            model: $model,
            timeout: config('platform.ai.timeout'),
        );

        return new AiResponse(text: $response->text, model: $model ?? 'unknown');
    }
}

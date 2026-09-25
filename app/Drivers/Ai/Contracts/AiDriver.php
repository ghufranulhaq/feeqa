<?php

namespace App\Drivers\Ai\Contracts;

use App\Drivers\Ai\AiResponse;

/**
 * Low-level AI text completion (constitution §5.1, plan D11). Feature code
 * (review summaries, reply suggestions, case triage, ...) owns its own
 * prompts and its own fallback behaviour when AI is unavailable — this
 * contract only answers "is AI on, and if so, what does it say".
 */
interface AiDriver
{
    public function isAvailable(): bool;

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws \RuntimeException if !$this->isAvailable()
     */
    public function complete(string $prompt, array $options = []): AiResponse;
}

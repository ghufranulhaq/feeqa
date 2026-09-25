<?php

namespace App\Drivers\Ai;

/**
 * P7 (explainable, supervised AI): every AI output must be traceable to its
 * inputs and model version. Feature code stores $model and $promptVersion
 * alongside the output it produces from $text.
 */
final class AiResponse
{
    public function __construct(
        public readonly string $text,
        public readonly string $model,
    ) {}
}

<?php

namespace App\Drivers\Transcription;

final class TranscriptionResult
{
    public function __construct(
        public readonly string $text,
        public readonly bool $isPlaceholder,
    ) {}
}

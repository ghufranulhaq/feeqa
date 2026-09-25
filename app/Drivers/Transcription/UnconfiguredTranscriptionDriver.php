<?php

namespace App\Drivers\Transcription;

use App\Drivers\Transcription\Contracts\TranscriptionDriver;
use RuntimeException;

/**
 * Bound for any TRANSCRIPTION_DRIVER value other than "fake". Plan D12a
 * defers the real provider choice to before launch — this exists so a
 * misconfigured .env fails loudly instead of silently, and so
 * production's boot check (App\Support\Environment) has something real
 * to point at once a provider is chosen (spec 012 build step).
 */
class UnconfiguredTranscriptionDriver implements TranscriptionDriver
{
    public function transcribe(string $filePath): TranscriptionResult
    {
        throw new RuntimeException(
            'No real transcription provider is wired up yet (plan D12a: chosen before launch). Set TRANSCRIPTION_DRIVER=fake for now.'
        );
    }
}

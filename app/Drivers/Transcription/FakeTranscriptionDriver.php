<?php

namespace App\Drivers\Transcription;

use App\Drivers\Transcription\Contracts\TranscriptionDriver;

/**
 * TRANSCRIPTION_DRIVER=fake (plan D12a): new uploads get a placeholder
 * transcript that the author can edit (the spec allows editing). Seeded
 * demo media reviews ship their own realistic, pre-written transcripts
 * instead of calling this driver at all.
 */
class FakeTranscriptionDriver implements TranscriptionDriver
{
    public function transcribe(string $filePath): TranscriptionResult
    {
        return new TranscriptionResult(
            text: '[Transcript not yet generated — edit this to add your own captions.]',
            isPlaceholder: true,
        );
    }
}

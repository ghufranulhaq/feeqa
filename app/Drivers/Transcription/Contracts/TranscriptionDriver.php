<?php

namespace App\Drivers\Transcription\Contracts;

use App\Drivers\Transcription\TranscriptionResult;

/**
 * Transcribes a voice/video review upload (plan D12a).
 */
interface TranscriptionDriver
{
    public function transcribe(string $filePath): TranscriptionResult;
}

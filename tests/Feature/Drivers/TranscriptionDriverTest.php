<?php

use App\Drivers\Transcription\Contracts\TranscriptionDriver;
use App\Drivers\Transcription\FakeTranscriptionDriver;
use App\Drivers\Transcription\UnconfiguredTranscriptionDriver;

it('resolves the fake driver when TRANSCRIPTION_DRIVER=fake', function () {
    config(['platform.transcription.driver' => 'fake']);
    app()->forgetInstance(TranscriptionDriver::class);

    expect(app(TranscriptionDriver::class))->toBeInstanceOf(FakeTranscriptionDriver::class);
});

it('resolves the unconfigured driver for any other value (plan D12a: chosen before launch)', function () {
    config(['platform.transcription.driver' => 'some-real-provider']);
    app()->forgetInstance(TranscriptionDriver::class);

    expect(app(TranscriptionDriver::class))->toBeInstanceOf(UnconfiguredTranscriptionDriver::class);
});

it('returns an editable placeholder transcript', function () {
    $result = (new FakeTranscriptionDriver)->transcribe('/tmp/clip.mp4');

    expect($result->isPlaceholder)->toBeTrue()
        ->and($result->text)->not->toBeEmpty();
});

it('fails loudly when no real provider is configured', function () {
    (new UnconfiguredTranscriptionDriver)->transcribe('/tmp/clip.mp4');
})->throws(RuntimeException::class);

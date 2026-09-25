<?php

use App\Drivers\Verification\Contracts\VerificationExtractor;
use App\Drivers\Verification\FakeVerificationExtractor;
use App\Drivers\Verification\OcrLlmVerificationExtractor;

it('resolves the fake extractor when VERIFICATION_EXTRACTOR=fake', function () {
    config(['platform.verification.extractor' => 'fake']);
    app()->forgetInstance(VerificationExtractor::class);

    expect(app(VerificationExtractor::class))->toBeInstanceOf(FakeVerificationExtractor::class);
});

it('resolves the OCR+LLM extractor for any other value', function () {
    config(['platform.verification.extractor' => 'ocr_llm']);
    app()->forgetInstance(VerificationExtractor::class);

    expect(app(VerificationExtractor::class))->toBeInstanceOf(OcrLlmVerificationExtractor::class);
});

it('returns plausible values matching the reviewed business (plan D12)', function () {
    $result = (new FakeVerificationExtractor)->extract('/tmp/whatever.pdf', [
        'business_name' => 'Skyline Airways',
        'experience_date_from' => '2026-08-01',
        'experience_date_to' => '2026-08-31',
        'currency' => 'EUR',
    ]);

    expect($result->merchant)->toBe('Skyline Airways')
        ->and($result->date->between('2026-08-01', '2026-08-31 23:59:59'))->toBeTrue()
        ->and($result->reference)->toStartWith('BK')
        ->and($result->amountMinorUnits)->toBeGreaterThan(0)
        ->and($result->currency)->toBe('EUR')
        ->and($result->confidence)->toBeGreaterThan(0);
});

it('falls back sensibly when no context is given', function () {
    $result = (new FakeVerificationExtractor)->extract('/tmp/whatever.pdf');

    expect($result->merchant)->toBe('Unknown Merchant')
        ->and($result->currency)->toBe('GBP');
});

it('has not implemented the real extractor yet', function () {
    (new OcrLlmVerificationExtractor)->extract('/tmp/whatever.pdf');
})->throws(RuntimeException::class);

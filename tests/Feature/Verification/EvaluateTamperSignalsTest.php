<?php

use App\Domain\Verification\EvaluateTamperSignals;

beforeEach(function () {
    config([
        'platform.verification.editing_tool_blocklist' => ['photoshop', 'gimp'],
        'platform.verification.known_fake_template_hashes' => ['deadbeefdeadbeef'],
    ]);
});

it('passes clean proofs with no signals (FR-004-07)', function () {
    $result = (new EvaluateTamperSignals)->handle(null, false, 'abc1230000000000');

    expect($result->passed)->toBeTrue()
        ->and($result->reasonCode)->toBeNull();
});

it('fails on an EXIF Software tag matching the editing-tool blocklist', function () {
    $result = (new EvaluateTamperSignals)->handle('Adobe Photoshop 25.0', false, null);

    expect($result->passed)->toBeFalse()
        ->and($result->reasonCode)->toBe('editing_software_detected');
});

it('is case-insensitive when matching the editing-tool blocklist', function () {
    $result = (new EvaluateTamperSignals)->handle('GIMP 2.10', false, null);

    expect($result->passed)->toBeFalse()
        ->and($result->reasonCode)->toBe('editing_software_detected');
});

it('does not flag an EXIF Software tag outside the blocklist', function () {
    $result = (new EvaluateTamperSignals)->handle('Apple Camera+', false, null);

    expect($result->passed)->toBeTrue();
});

it('fails when the image near-duplicates another account\'s proof', function () {
    $result = (new EvaluateTamperSignals)->handle(null, true, null);

    expect($result->passed)->toBeFalse()
        ->and($result->reasonCode)->toBe('near_duplicate_image_other_account');
});

it('fails on a perceptual hash matching a known fake template', function () {
    $result = (new EvaluateTamperSignals)->handle(null, false, 'deadbeefdeadbeef');

    expect($result->passed)->toBeFalse()
        ->and($result->reasonCode)->toBe('known_fake_template');
});

it('matches known fake templates case-insensitively', function () {
    $result = (new EvaluateTamperSignals)->handle(null, false, 'DEADBEEFDEADBEEF');

    expect($result->passed)->toBeFalse()
        ->and($result->reasonCode)->toBe('known_fake_template');
});

it('checks EXIF signature before the other signals', function () {
    $result = (new EvaluateTamperSignals)->handle('Adobe Photoshop', true, 'deadbeefdeadbeef');

    expect($result->reasonCode)->toBe('editing_software_detected');
});

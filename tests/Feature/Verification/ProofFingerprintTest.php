<?php

use App\Domain\Verification\ProofFingerprint;

it('produces the same fingerprint for the same business and reference (FR-004-10)', function () {
    $first = ProofFingerprint::forReference(42, 'SK-448812');
    $second = ProofFingerprint::forReference(42, 'SK-448812');

    expect($first)->toBe($second);
});

it('normalises punctuation and case so equivalent references collide (FR-004-10)', function () {
    $plain = ProofFingerprint::forReference(42, 'SK-448812');
    $spaced = ProofFingerprint::forReference(42, ' sk 448812 ');

    expect($plain)->toBe($spaced);
});

it('differs when the business differs, even with the same reference', function () {
    $businessA = ProofFingerprint::forReference(42, 'SK-448812');
    $businessB = ProofFingerprint::forReference(43, 'SK-448812');

    expect($businessA)->not->toBe($businessB);
});

it('differs when the reference differs, even for the same business', function () {
    $one = ProofFingerprint::forReference(42, 'SK-448812');
    $two = ProofFingerprint::forReference(42, 'SK-448813');

    expect($one)->not->toBe($two);
});

it('folds the perceptual hash into the document fingerprint so two documents with the same reference differ', function () {
    $documentA = ProofFingerprint::forDocument(42, 'SK-448812', 'aaaaaaaaaaaaaaaa');
    $documentB = ProofFingerprint::forDocument(42, 'SK-448812', 'bbbbbbbbbbbbbbbb');

    expect($documentA)->not->toBe($documentB)
        ->and($documentA)->not->toBe(ProofFingerprint::forReference(42, 'SK-448812'));
});

it('throws when no fingerprint key is configured', function () {
    config(['platform.verification.fingerprint_key' => null]);

    ProofFingerprint::forReference(42, 'SK-448812');
})->throws(RuntimeException::class, 'VERIFICATION_FINGERPRINT_KEY');

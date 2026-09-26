<?php

use App\Domain\Verification\TransactionRecordHash;

it('hashes a reference deterministically regardless of formatting (FR-004-12)', function () {
    $a = TransactionRecordHash::reference('ABC-123');
    $b = TransactionRecordHash::reference('abc123');

    expect($a)->toBe($b)
        ->and($a)->toMatch('/^[a-f0-9]{64}$/');
});

it('hashes an email deterministically regardless of case or whitespace', function () {
    $a = TransactionRecordHash::email('Jane@Example.com');
    $b = TransactionRecordHash::email(' jane@example.com ');

    expect($a)->toBe($b)
        ->and($a)->toMatch('/^[a-f0-9]{64}$/');
});

it('recognises a real hash and rejects a plaintext-shaped value', function () {
    $hash = TransactionRecordHash::reference('ABC-123');

    expect(TransactionRecordHash::looksHashed($hash))->toBeTrue()
        ->and(TransactionRecordHash::looksHashed('jane@example.com'))->toBeFalse()
        ->and(TransactionRecordHash::looksHashed('ABC-123'))->toBeFalse();
});

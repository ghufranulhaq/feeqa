<?php

use App\Domain\Verification\ContainsPan;

it('detects a Luhn-valid card number (FR-004-09)', function () {
    // A well-known Luhn-valid test PAN.
    expect(ContainsPan::check('Card on file: 4111 1111 1111 1111, thanks!'))->toBeTrue();
});

it('detects a card number written with dashes', function () {
    expect(ContainsPan::check('4111-1111-1111-1111'))->toBeTrue();
});

it('detects a card number with no separators', function () {
    expect(ContainsPan::check('Reference 4111111111111111 confirmed'))->toBeTrue();
});

it('ignores a long digit run that fails the Luhn check', function () {
    expect(ContainsPan::check('Booking reference 1234567890123456'))->toBeFalse();
});

it('ignores ordinary short reference numbers', function () {
    expect(ContainsPan::check('Booking reference BK123456, total 45.00 GBP'))->toBeFalse();
});

it('ignores plain merchant text with no digit runs', function () {
    expect(ContainsPan::check('Skyline Airways — thank you for flying with us'))->toBeFalse();
});

<?php

use App\Domain\Verification\MerchantMatch;

it('matches an exact business name (FR-004-06)', function () {
    expect(MerchantMatch::matches('Skyline Airways', ['Skyline Airways', 'skyline-airways.com']))->toBeTrue();
});

it('matches case- and punctuation-insensitively', function () {
    expect(MerchantMatch::matches('SKYLINE AIRWAYS LTD.', ['Skyline Airways Ltd']))->toBeTrue();
});

it('matches a domain, ignoring a leading www.', function () {
    expect(MerchantMatch::matches('www.skyline-airways.com', ['skyline-airways.com']))->toBeTrue();
});

it('matches a slightly misspelled merchant name against a high-similarity candidate', function () {
    expect(MerchantMatch::matches('Skyline Airwyas', ['Skyline Airways']))->toBeTrue();
});

it('does not match a clearly different business (edge case: proof names a different business)', function () {
    expect(MerchantMatch::matches('Bluewave Hotels', ['Skyline Airways', 'skyline-airways.com']))->toBeFalse();
});

it('matches against a registered alias (additional domain)', function () {
    expect(MerchantMatch::matches('legacy-brand.com', ['Skyline Airways', 'skyline-airways.com', 'legacy-brand.com']))->toBeTrue();
});

it('does not match an empty extracted merchant', function () {
    expect(MerchantMatch::matches('', ['Skyline Airways']))->toBeFalse();
});

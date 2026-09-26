<?php

use App\Domain\Businesses\DomainNormalizer;

it('normalises protocol, path, and case (edge cases table)', function (string $input, string $expected) {
    expect(DomainNormalizer::normalize($input))->toBe($expected);
})->with([
    ['https://www.Skyhop-Travel.com/about', 'skyhop-travel.com'],
    ['http://skyhop-travel.com', 'skyhop-travel.com'],
    ['skyhop-travel.com', 'skyhop-travel.com'],
    ['SKYHOP-TRAVEL.COM', 'skyhop-travel.com'],
    ['www.skyhop-travel.com', 'skyhop-travel.com'],
]);

it('leaves a non-www subdomain alone, treating it as a different host (edge cases table)', function () {
    expect(DomainNormalizer::normalize('shop.brand.com'))->toBe('shop.brand.com')
        ->and(DomainNormalizer::normalize('brand.com'))->toBe('brand.com');
});

it('converts an IDN domain to punycode (FR-002-09)', function () {
    expect(DomainNormalizer::normalize('münchen-tours.de'))->toBe('xn--mnchen-tours-dlb.de');
});

it('returns null for empty or unparseable input', function (string $input) {
    expect(DomainNormalizer::normalize($input))->toBeNull();
})->with(['', '   ', 'https://']);

<?php

use App\Rules\DisplayName;
use Illuminate\Support\Facades\Validator;

function validateDisplayName(string $name): bool
{
    return Validator::make(['name' => $name], ['name' => [new DisplayName]])->fails();
}

it('accepts an ordinary display name', function () {
    expect(validateDisplayName('Jordan R.'))->toBeFalse();
});

it('rejects an empty or whitespace-only name', function (string $name) {
    expect(validateDisplayName($name))->toBeTrue();
})->with(['', '   ', "\t\n"]);

it('rejects a single-character name', function () {
    expect(validateDisplayName('J'))->toBeTrue();
});

it('rejects a name over 40 characters', function () {
    expect(validateDisplayName(str_repeat('a', 41)))->toBeTrue();
});

it('accepts exactly 40 characters', function () {
    expect(validateDisplayName(str_repeat('a', 40)))->toBeFalse();
});

it('rejects a name containing a URL', function (string $name) {
    expect(validateDisplayName($name))->toBeTrue();
})->with([
    'Visit https://example.com',
    'www.example.com fan',
    'check-out example.com now',
]);

it('rejects a name containing an email address', function () {
    expect(validateDisplayName('contact me at person@example.com'))->toBeTrue();
});

it('rejects a name containing a phone number', function () {
    expect(validateDisplayName('Call me 07911 123456'))->toBeTrue();
});

it('rejects a name impersonating a staff role', function (string $name) {
    expect(validateDisplayName($name))->toBeTrue();
})->with([
    'Admin',
    'Site Admin',
    'Moderator',
    'Senior Moderator Team',
    'Support',
    'Mediator',
]);

it('does not falsely flag a name that merely contains a role word as a substring', function () {
    expect(validateDisplayName('Adminia Smith'))->toBeFalse();
});

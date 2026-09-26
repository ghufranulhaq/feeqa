<?php

use App\Rules\BusinessDescription;
use Illuminate\Support\Facades\Validator;

function validateDescription(string $description, ?string $ownDomain = null): Illuminate\Contracts\Validation\Validator
{
    return Validator::make(
        ['description' => $description],
        ['description' => [new BusinessDescription($ownDomain)]],
    );
}

it('accepts an ordinary description', function () {
    expect(validateDescription('A friendly regional airline.')->passes())->toBeTrue();
});

it('accepts a link to the business\'s own domain', function () {
    expect(validateDescription('Book at https://www.skyhop-travel.com/deals', 'skyhop-travel.com')->passes())->toBeTrue();
});

it('rejects a link to a different domain (edge cases table)', function () {
    expect(validateDescription('Better deals at rival-airline.com!', 'skyhop-travel.com')->fails())->toBeTrue();
});

it('rejects any link when the business has no domain of its own', function () {
    expect(validateDescription('Visit www.example.com for more', null)->fails())->toBeTrue();
});

it('rejects a description over 1500 characters (FR-002-03)', function () {
    expect(validateDescription(str_repeat('a', 1501))->fails())->toBeTrue();
});

it('accepts exactly 1500 characters', function () {
    expect(validateDescription(str_repeat('a', 1500))->passes())->toBeTrue();
});

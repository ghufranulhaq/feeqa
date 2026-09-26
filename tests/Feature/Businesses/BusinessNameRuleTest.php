<?php

use App\Rules\BusinessName;
use Illuminate\Support\Facades\Validator;

function validateBusinessName(string $name): Illuminate\Contracts\Validation\Validator
{
    return Validator::make(['name' => $name], ['name' => [new BusinessName]]);
}

it('accepts an ordinary business name, including a domain-shaped one', function (string $name) {
    expect(validateBusinessName($name)->passes())->toBeTrue();
})->with(['Skyhop Travel', 'Booking.com', 'easyJet']);

it('rejects a phone number in the name (edge cases table)', function () {
    expect(validateBusinessName('Call us 020-7946-0958')->fails())->toBeTrue();
});

it('rejects an ALL-CAPS name (edge cases table)', function () {
    expect(validateBusinessName('BEST TRAVEL DEALS')->fails())->toBeTrue();
});

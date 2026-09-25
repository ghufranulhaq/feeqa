<?php

use App\Drivers\Billing\Contracts\BillingDriver;
use App\Drivers\Billing\FakeBillingDriver;
use App\Drivers\Billing\StripeBillingDriver;

it('resolves the fake driver when BILLING_DRIVER=fake', function () {
    config(['platform.billing.driver' => 'fake']);
    app()->forgetInstance(BillingDriver::class);

    expect(app(BillingDriver::class))->toBeInstanceOf(FakeBillingDriver::class);
});

it('resolves Stripe for any other value', function () {
    config(['platform.billing.driver' => 'stripe']);
    app()->forgetInstance(BillingDriver::class);

    expect(app(BillingDriver::class))->toBeInstanceOf(StripeBillingDriver::class);
});

it('every payment attempt succeeds and keeps only the last 4 digits (plan D15)', function () {
    $result = (new FakeBillingDriver)->charge(4999, 'GBP', '4242424242424242');

    expect($result->success)->toBeTrue()
        ->and($result->cardLast4)->toBe('4242')
        ->and($result->reference)->toStartWith('FAKE-');
});

it('falls back to 4242 when no digits are given', function () {
    $result = (new FakeBillingDriver)->charge(4999, 'GBP', '');

    expect($result->cardLast4)->toBe('4242');
});

it('has not implemented Stripe yet', function () {
    (new StripeBillingDriver)->charge(4999, 'GBP', '4242424242424242');
})->throws(RuntimeException::class);

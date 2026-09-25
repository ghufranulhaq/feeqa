<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\Rules\Password;

uses(RefreshDatabase::class);

function registerWith(string $password): TestResponse
{
    return test()->post('/register', [
        'name' => 'Jordan Rivers',
        'email' => 'jordan-'.uniqid().'@example.com',
        'password' => $password,
        'password_confirmation' => $password,
    ]);
}

it('rejects a password shorter than the environment minimum (FR-001-04)', function () {
    config(['platform.security.check_breached_passwords' => false]);

    // The test environment's minimum is 6 (Environment::passwordMinLength).
    registerWith('abc12')->assertSessionHasErrors('password');
    expect(User::count())->toBe(0);
});

it('accepts a password meeting the environment minimum when breach checking is off', function () {
    config(['platform.security.check_breached_passwords' => false]);

    registerWith('abc123')->assertSessionDoesntHaveErrors('password');
    expect(User::count())->toBe(1);
});

// These two exercise Password::defaults() directly rather than through a
// full HTTP round trip: simulating APP_ENV=production mid-test also turns
// off VerifyCsrfToken's runningUnitTests() bypass, so an HTTP POST here
// would 419 before validation ever runs.
it('rejects an 11-character password in production', function () {
    app()['env'] = 'production';
    config(['platform.security.check_breached_passwords' => false]);

    $result = Validator::make(
        ['password' => 'eleven1234a'],
        ['password' => Password::defaults()]
    );

    expect($result->fails())->toBeTrue();
});

it('accepts a 12-character password in production', function () {
    app()['env'] = 'production';
    // Production always runs the breach check (Environment::checkBreachedPasswords
    // ignores this config value there) — fake the network call so this stays
    // a hermetic test of the length rule, not a live HIBP lookup.
    config(['platform.security.check_breached_passwords' => false]);
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

    $result = Validator::make(
        ['password' => 'twelvechars1'],
        ['password' => Password::defaults()]
    );

    expect($result->fails())->toBeFalse();
});

it('rejects a password found in a known breach when checking is on', function () {
    config(['platform.security.check_breached_passwords' => true]);

    $password = 'CorrectHorseBattery9';
    $hash = strtoupper(sha1($password));
    [$prefix, $suffix] = [substr($hash, 0, 5), substr($hash, 5)];

    Http::fake([
        "api.pwnedpasswords.com/range/{$prefix}" => Http::response("{$suffix}:5\n"),
    ]);

    registerWith($password)->assertSessionHasErrors('password');
    expect(User::count())->toBe(0);
});

it('accepts a password not found in the breach list when checking is on', function () {
    config(['platform.security.check_breached_passwords' => true]);

    Http::fake([
        'api.pwnedpasswords.com/*' => Http::response("00000AAAA1111BBBB2222CCCC3333DDDD444:2\n"),
    ]);

    registerWith('a-fresh-unbreached-1')->assertSessionDoesntHaveErrors('password');
    expect(User::count())->toBe(1);
});

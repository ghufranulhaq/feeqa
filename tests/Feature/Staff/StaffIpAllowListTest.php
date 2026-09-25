<?php

use App\Domain\Staff\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

/**
 * Every test here simulates a non-testing APP_ENV, which also turns off
 * the CSRF middleware's runningUnitTests() bypass — so a matching session
 * token is sent explicitly instead of trying to disable that middleware.
 */
beforeEach(function () {
    $this->withSession(['_token' => 'test-csrf-token']);
});

function postWithCsrf(TestCase $test, string $url, array $data)
{
    return $test->post($url, array_merge($data, ['_token' => 'test-csrf-token']));
}

it('allows any IP outside production (constitution §5.6)', function () {
    app()['env'] = 'demo';
    config(['platform.staff.allowed_ips' => ['203.0.113.9']]);
    $user = User::factory()->create();

    $response = postWithCsrf(
        $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '198.51.100.1']),
        '/staff/accounts',
        ['name' => 'X', 'email' => 'x@example.com', 'role' => 'Support'],
    );

    // Passed the IP gate; this 403 is the action's own staff-Admin check.
    $response->assertForbidden();
    $response->assertSeeText('Only a staff Admin can create staff accounts.');
});

it('rejects a request from outside the allow-list in production (FR-001-14)', function () {
    app()['env'] = 'production';
    config(['platform.staff.allowed_ips' => ['203.0.113.9']]);
    $user = User::factory()->create();

    $response = postWithCsrf(
        $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '198.51.100.1']),
        '/staff/accounts',
        ['name' => 'X', 'email' => 'x@example.com', 'role' => 'Support'],
    );

    $response->assertForbidden();
    $response->assertSeeText('This network is not allowed to reach the staff console.');
});

it('allows a request from an allow-listed IP in production', function () {
    app()['env'] = 'production';
    config(['platform.staff.allowed_ips' => ['203.0.113.9'], 'platform.security.check_breached_passwords' => false]);
    $admin = User::factory()->create();
    $admin->forceFill(['staff_role' => StaffRole::Admin->value])->save();

    $response = postWithCsrf(
        $this->actingAs($admin)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9']),
        '/staff/accounts',
        ['name' => 'X', 'email' => 'allowed@example.com', 'role' => 'Support'],
    );

    // Not a 403 from the IP gate — it gets through to the controller.
    $response->assertSessionHasNoErrors();
    expect(User::where('email', 'allowed@example.com')->exists())->toBeTrue();
});

it('allows a CIDR range', function () {
    app()['env'] = 'production';
    config(['platform.staff.allowed_ips' => ['203.0.113.0/24']]);
    $user = User::factory()->create();

    $response = postWithCsrf(
        $this->actingAs($user)->withServerVariables(['REMOTE_ADDR' => '203.0.113.200']),
        '/staff/accounts',
        ['name' => 'X', 'email' => 'x@example.com', 'role' => 'Support'],
    );

    // Passed the IP gate; 403 here is the action's own staff-Admin check.
    $response->assertForbidden();
    $response->assertSeeText('Only a staff Admin can create staff accounts.');
});

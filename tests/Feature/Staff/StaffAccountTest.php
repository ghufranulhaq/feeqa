<?php

use App\Actions\Staff\CreateStaffAccount;
use App\Domain\Staff\StaffRole;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

function staffAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => StaffRole::Admin->value])->save();

    return $user;
}

it('lets a staff Admin create a staff account (FR-001-14)', function () {
    Password::shouldReceive('sendResetLink')->once();
    $admin = staffAdmin();

    $this->actingAs($admin)
        ->post('/staff/accounts', [
            'name' => 'New Moderator',
            'email' => 'moderator@example.com',
            'role' => StaffRole::Moderator->value,
        ])
        ->assertSessionHasNoErrors();

    $created = User::where('email', 'moderator@example.com')->firstOrFail();
    expect($created->staffRole())->toBe(StaffRole::Moderator);
    expect($created->isStaff())->toBeTrue();
});

it('rejects a non-Admin staff member creating a staff account (FR-001-14)', function () {
    $moderator = User::factory()->create();
    $moderator->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    $this->actingAs($moderator)
        ->post('/staff/accounts', [
            'name' => 'New Moderator',
            'email' => 'another@example.com',
            'role' => StaffRole::Support->value,
        ])
        ->assertForbidden();

    expect(User::where('email', 'another@example.com')->exists())->toBeFalse();
});

it('rejects a plain consumer creating a staff account', function () {
    $consumer = User::factory()->create();

    $this->actingAs($consumer)
        ->post('/staff/accounts', [
            'name' => 'New Moderator',
            'email' => 'another@example.com',
            'role' => StaffRole::Support->value,
        ])
        ->assertForbidden();
});

it('writes a compliance log entry when a staff account is created (FR-001-15)', function () {
    Password::shouldReceive('sendResetLink')->once();
    $admin = staffAdmin();

    $created = (new CreateStaffAccount)->handle(
        $admin,
        'New Moderator',
        'moderator@example.com',
        StaffRole::Moderator,
    );

    $entry = ComplianceLogEntry::where('staff_id', $admin->id)->firstOrFail();
    expect($entry->action)->toBe('staff_account_created');
    expect($entry->target_type)->toBe($created->getMorphClass());
    expect($entry->target_id)->toBe($created->id);
    expect($entry->reason_code)->not->toBeEmpty();
});

it('throws instead of creating an account when the actor is not a staff Admin', function () {
    $notAdmin = User::factory()->create();

    expect(fn () => (new CreateStaffAccount)->handle($notAdmin, 'X', 'x@example.com', StaffRole::Support))
        ->toThrow(AuthorizationException::class);
});

<?php

use App\Actions\Staff\BlockUserAccount;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Staff\StaffRole;
use App\Models\ComplianceLogEntry;
use App\Models\User;
use App\Notifications\StatementOfReasonsNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('blocks an account, logs, and sends a statement of reasons (FR-006-15)', function () {
    Notification::fake();
    $staff = User::factory()->create(['staff_role' => StaffRole::Moderator->value]);
    $target = User::factory()->create();

    $result = (new BlockUserAccount)->handle($staff, $target, ReasonCode::HarmfulIllegal);

    expect($result->isBlocked())->toBeTrue()
        ->and($result->blocked_reason)->toBe(ReasonCode::HarmfulIllegal->value);
    Notification::assertSentTo($target, StatementOfReasonsNotification::class);
    expect(ComplianceLogEntry::where('target_id', $target->id)->where('action', 'user_blocked')->exists())->toBeTrue();
});

it('rejects blocking an already-blocked account', function () {
    $staff = User::factory()->create(['staff_role' => StaffRole::Moderator->value]);
    $target = User::factory()->create(['blocked_at' => now()]);

    (new BlockUserAccount)->handle($staff, $target, ReasonCode::HarmfulIllegal);
})->throws(ValidationException::class);

it('rejects a non-staff user blocking an account', function () {
    $target = User::factory()->create();

    (new BlockUserAccount)->handle(User::factory()->create(), $target, ReasonCode::HarmfulIllegal);
})->throws(AuthorizationException::class);

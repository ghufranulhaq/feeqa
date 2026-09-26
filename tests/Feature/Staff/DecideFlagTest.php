<?php

use App\Actions\Moderation\CreateFlag;
use App\Actions\Staff\DecideFlag;
use App\Domain\Moderation\FlagStatus;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ComplianceLogEntry;
use App\Models\ModeratorConflict;
use App\Models\Review;
use App\Models\User;
use App\Notifications\FlagDecidedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function decideFlagModerator(): User
{
    return User::factory()->create(['staff_role' => StaffRole::Moderator->value]);
}

it('upholds a flag, notifies the signed-in reporter, and restores the review once it leaves blurred (User Scenario 2)', function () {
    Notification::fake();
    $review = Review::factory()->create();
    $reporter = User::factory()->create();
    $flag = (new CreateFlag)->handle($review, ReasonCode::PersonalInfo, reporter: $reporter);
    expect($flag->status)->toBe(FlagStatus::Blurred);

    $decided = (new DecideFlag)->handle(decideFlagModerator(), $flag, FlagStatus::Upheld, ReasonCode::PersonalInfo, 'Phone number redacted.');

    expect($decided->status)->toBe(FlagStatus::Upheld)
        ->and($decided->decided_at)->not->toBeNull()
        ->and($review->fresh()->isBlurred())->toBeFalse();
    Notification::assertSentTo($reporter, FlagDecidedNotification::class);
    expect(ComplianceLogEntry::where('target_id', $flag->id)->where('action', 'flag_upheld')->exists())->toBeTrue();
});

it('notifies a guest reporter by email on decision', function () {
    Notification::fake();
    $review = Review::factory()->create();
    $flag = (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporterEmail: 'guest@example.com');

    (new DecideFlag)->handle(decideFlagModerator(), $flag, FlagStatus::Rejected, ReasonCode::AdvertisingSpam, 'Not spam.');

    Notification::assertSentOnDemand(FlagDecidedNotification::class, function ($notification, $channels, $notifiable) {
        return $notifiable->routes['mail'] === 'guest@example.com';
    });
});

it('rejects deciding a flag with a status other than upheld or rejected', function () {
    $review = Review::factory()->create();
    $flag = (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporterEmail: 'guest@example.com');

    (new DecideFlag)->handle(decideFlagModerator(), $flag, FlagStatus::Open, ReasonCode::AdvertisingSpam, 'n/a');
})->throws(ValidationException::class);

it('rejects deciding an already-decided flag', function () {
    $review = Review::factory()->create();
    $flag = (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporterEmail: 'guest@example.com');
    $staff = decideFlagModerator();
    (new DecideFlag)->handle($staff, $flag, FlagStatus::Rejected, ReasonCode::AdvertisingSpam, 'Not spam.');

    (new DecideFlag)->handle($staff, $flag, FlagStatus::Upheld, ReasonCode::AdvertisingSpam, 'Changed my mind.');
})->throws(ValidationException::class);

it('rejects a non-staff user deciding a flag', function () {
    $review = Review::factory()->create();
    $flag = (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporterEmail: 'guest@example.com');

    (new DecideFlag)->handle(User::factory()->create(), $flag, FlagStatus::Rejected, ReasonCode::AdvertisingSpam, 'n/a');
})->throws(AuthorizationException::class);

it('blocks a moderator with a declared conflict of interest on the flagged review\'s business (edge case table)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $flag = (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporterEmail: 'guest@example.com');
    $staff = decideFlagModerator();
    ModeratorConflict::factory()->create(['staff_id' => $staff->id, 'business_id' => $business->id]);

    (new DecideFlag)->handle($staff, $flag, FlagStatus::Rejected, ReasonCode::AdvertisingSpam, 'n/a');
})->throws(AuthorizationException::class);

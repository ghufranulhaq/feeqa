<?php

use App\Actions\Moderation\CreateFlag;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Moderation\FlagStatus;
use App\Domain\Moderation\ReasonCode;
use App\Models\Business;
use App\Models\Flag;
use App\Models\Review;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function flagReporterMember(Business $business, BusinessRole $role = BusinessRole::Owner): User
{
    (new BusinessRolesSeeder)->run();
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

it('lets a guest flag a review with an email address (FR-006-07)', function () {
    $review = Review::factory()->create();

    $flag = (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporterEmail: 'guest@example.com');

    expect($flag->reporter_email)->toBe('guest@example.com')
        ->and($flag->status)->toBe(FlagStatus::Open)
        ->and(abs($flag->sla_due_at->diffInHours(now())))->toBeGreaterThan(24 * 6);
});

it('rejects a flag with no reason code', function () {
    $review = Review::factory()->create();

    (new CreateFlag)->handle($review, null, reporterEmail: 'guest@example.com');
})->throws(ValidationException::class);

it('rejects an anonymous flag with no email address', function () {
    $review = Review::factory()->create();

    (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam);
})->throws(ValidationException::class);

it('rejects details over 1,000 characters', function () {
    $review = Review::factory()->create();

    (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporterEmail: 'guest@example.com', details: str_repeat('a', 1001));
})->throws(ValidationException::class);

it('rejects more than 5 evidence files', function () {
    $review = Review::factory()->create();
    $evidence = array_fill(0, 6, ['path' => 'evidence.jpg', 'size' => 1024]);

    (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporterEmail: 'guest@example.com', evidence: $evidence);
})->throws(ValidationException::class);

it('rejects an evidence file over 10 MB', function () {
    $review = Review::factory()->create();
    $evidence = [['path' => 'evidence.mp4', 'size' => 11 * 1024 * 1024]];

    (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporterEmail: 'guest@example.com', evidence: $evidence);
})->throws(ValidationException::class);

it('blurs a harmful_illegal flag from a trusted reporter immediately (FR-006-08)', function () {
    $review = Review::factory()->create();
    $reporter = User::factory()->create();

    $flag = (new CreateFlag)->handle($review, ReasonCode::HarmfulIllegal, reporter: $reporter);

    expect($flag->status)->toBe(FlagStatus::Blurred)
        ->and(abs($flag->sla_due_at->diffInMinutes(now())))->toBeGreaterThan(23 * 60);

    $review->refresh();
    expect($review->isBlurred())->toBeTrue()
        ->and(Review::publiclyVisible()->whereKey($review->id)->exists())->toBeFalse();
});

it('blurs personal_info content once 3 distinct guest reporters flag it (FR-006-08)', function () {
    $review = Review::factory()->create();

    $first = (new CreateFlag)->handle($review, ReasonCode::PersonalInfo, reporterEmail: 'one@example.com');
    $second = (new CreateFlag)->handle($review, ReasonCode::PersonalInfo, reporterEmail: 'two@example.com');
    $third = (new CreateFlag)->handle($review, ReasonCode::PersonalInfo, reporterEmail: 'three@example.com');

    // Guests have no compliance-history signal, so trust can't be
    // established for them — only the 3-distinct-reporter path applies.
    expect($first->status)->toBe(FlagStatus::Open)
        ->and($second->status)->toBe(FlagStatus::Open)
        ->and($third->status)->toBe(FlagStatus::Blurred);
});

it('is a no-op when the same reporter flags the same item twice', function () {
    $review = Review::factory()->create();
    $reporter = User::factory()->create();

    $first = (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporter: $reporter);
    $second = (new CreateFlag)->handle($review, ReasonCode::NotGenuine, reporter: $reporter);

    expect($second->id)->toBe($first->id)
        ->and(Flag::count())->toBe(1);
});

it('re-runs screening when a business flags a review as not_genuine (FR-006-09)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $owner = flagReporterMember($business);

    expect(Screening::where('screenable_id', $review->id)->count())->toBe(0);

    (new CreateFlag)->handle($review, ReasonCode::NotGenuine, reporter: $owner, actingBusiness: $business);

    expect(Screening::where('screenable_id', $review->id)->count())->toBe(1);
});

it('never lets a business flag hide a review by itself, even for harmful_illegal (FR-006-09)', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $owner = flagReporterMember($business);

    $flag = (new CreateFlag)->handle($review, ReasonCode::HarmfulIllegal, reporter: $owner, actingBusiness: $business);

    expect($flag->status)->toBe(FlagStatus::Open)
        ->and($review->fresh()->isBlurred())->toBeFalse();
});

it('rejects a business flag from a member without the FlagReviews permission', function () {
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create();
    $analyst = flagReporterMember($business, BusinessRole::Analyst);

    (new CreateFlag)->handle($review, ReasonCode::NotGenuine, reporter: $analyst, actingBusiness: $business);
})->throws(AuthorizationException::class);

it('enforces the 50-open-flag cap per business (FR-006-10)', function () {
    $business = Business::factory()->create();
    $owner = flagReporterMember($business);

    Flag::factory()->count(50)->create([
        'business_id' => $business->id,
        'is_business_flag' => true,
        'status' => FlagStatus::Open,
    ]);

    $review = Review::factory()->for($business)->create();

    (new CreateFlag)->handle($review, ReasonCode::NotGenuine, reporter: $owner, actingBusiness: $business);
})->throws(ValidationException::class);

it('logs a fraud signal when a business flags are rejected more than 80% of the time (FR-006-10)', function () {
    $business = Business::factory()->create();
    $owner = flagReporterMember($business);

    Flag::factory()->count(17)->create([
        'business_id' => $business->id,
        'is_business_flag' => true,
        'status' => FlagStatus::Rejected,
    ]);
    Flag::factory()->count(3)->create([
        'business_id' => $business->id,
        'is_business_flag' => true,
        'status' => FlagStatus::Open,
    ]);

    Log::spy();

    $review = Review::factory()->for($business)->create();
    (new CreateFlag)->handle($review, ReasonCode::NotGenuine, reporter: $owner, actingBusiness: $business);

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message) => str_contains($message, 'FR-006-10')
    )->once();
});

it('logs a fraud signal for mass flagging by one account (edge case table)', function () {
    $reporter = User::factory()->create();

    Flag::factory()->count(20)->create(['reporter_id' => $reporter->id]);

    Log::spy();

    $review = Review::factory()->create();
    (new CreateFlag)->handle($review, ReasonCode::AdvertisingSpam, reporter: $reporter);

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message) => str_contains($message, 'FR-006-10')
    )->once();
});

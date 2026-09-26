<?php

use App\Actions\Moderation\ListGuidelineVersions;
use App\Actions\Staff\PublishGuidelineVersion;
use App\Domain\Moderation\GuidelineAudience;
use App\Domain\Staff\StaffRole;
use App\Models\GuidelineVersion;
use App\Models\User;
use Database\Seeders\Base\GuidelineVersionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function admin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['staff_role' => StaffRole::Admin->value])->save();

    return $user;
}

it('publishes v1 as current for both audiences from the seeder (FR-006-01)', function () {
    $this->seed(GuidelineVersionsSeeder::class);

    $result = (new ListGuidelineVersions)->handle();

    expect($result[GuidelineAudience::Reviewer->value]['current']->version)->toBe(1)
        ->and($result[GuidelineAudience::Business->value]['current']->version)->toBe(1);
});

it('publishes a new version, keeps the old one, and flips is_current (FR-006-01)', function () {
    GuidelineVersion::factory()->create([
        'audience' => GuidelineAudience::Reviewer,
        'version' => 1,
        'is_current' => true,
    ]);

    $v2 = (new PublishGuidelineVersion)->handle(admin(), GuidelineAudience::Reviewer, 'v2 body');

    expect($v2->version)->toBe(2)
        ->and($v2->is_current)->toBeTrue();

    $v1 = GuidelineVersion::where('audience', GuidelineAudience::Reviewer)->where('version', 1)->sole();
    expect($v1->is_current)->toBeFalse();

    $result = (new ListGuidelineVersions)->handle();
    expect($result[GuidelineAudience::Reviewer->value]['history'])->toHaveCount(2)
        ->and($result[GuidelineAudience::Reviewer->value]['current']->version)->toBe(2);
});

it('rejects a non-Admin publishing a guideline version', function () {
    $moderator = User::factory()->create();
    $moderator->forceFill(['staff_role' => StaffRole::Moderator->value])->save();

    (new PublishGuidelineVersion)->handle($moderator, GuidelineAudience::Business, 'body');
})->throws(AuthorizationException::class);

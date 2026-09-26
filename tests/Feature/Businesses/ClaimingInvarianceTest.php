<?php

use App\Actions\Businesses\StartBusinessClaim;
use App\Actions\Businesses\VerifyBusinessClaimCode;
use App\Domain\Businesses\ClaimMethod;
use App\Models\Business;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

/**
 * Acceptance criteria: "Claiming changes no review, score, or label
 * (checked with a before/after snapshot test)." Review/score models
 * don't exist yet (specs 003/008) — the meaningful snapshot available
 * today is every other profile field a visitor already sees.
 */
function snapshotOf(Business $business): array
{
    return collect($business->only([
        'id', 'slug', 'name', 'description', 'website', 'email', 'phone',
        'address', 'social_links', 'country', 'primary_category_id', 'logo_path',
    ]))->map(fn ($value) => $value instanceof Carbon ? $value->toDateTimeString() : $value)->all();
}

it('changes nothing about the profile except status and claimed_at (FR-002-15)', function () {
    $business = Business::factory()->create([
        'primary_domain' => 'skyhop-travel.com',
        'description' => 'A regional airline.',
        'website' => 'https://skyhop-travel.com',
    ]);
    $before = snapshotOf($business);

    $claimant = User::factory()->create();
    $claim = app(StartBusinessClaim::class)->handle($business, $claimant, ClaimMethod::Email, ['target' => 'jane@skyhop-travel.com']);
    app(VerifyBusinessClaimCode::class)->handle($claim, $claim->verification_code);

    $after = snapshotOf($business->fresh());

    expect($after)->toBe($before);
});

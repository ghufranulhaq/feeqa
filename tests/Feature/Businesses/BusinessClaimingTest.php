<?php

use App\Actions\Businesses\StartBusinessClaim;
use App\Actions\Businesses\VerifyBusinessClaimCode;
use App\Actions\Businesses\VerifyBusinessDomainClaim;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Businesses\BusinessStatus;
use App\Domain\Businesses\ClaimMethod;
use App\Domain\Businesses\ClaimStatus;
use App\Drivers\BusinessClaiming\Contracts\DomainClaimChecker;
use App\Drivers\Malware\Contracts\MalwareScanner;
use App\Drivers\Malware\ScanResult;
use App\Models\Business;
use App\Models\BusinessClaim;
use App\Models\User;
use App\Notifications\BusinessClaimCodeNotification;
use App\Notifications\BusinessReclaimRequestNotification;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

// --- StartBusinessClaim -----------------------------------------------

it('starts an email claim, generating a code and notifying the target address (FR-002-11a)', function () {
    Notification::fake();
    $business = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);
    $claimant = User::factory()->create();

    $claim = app(StartBusinessClaim::class)->handle($business, $claimant, ClaimMethod::Email, ['target' => 'jane@skyhop-travel.com']);

    expect($claim->verification_code)->toHaveLength(6)
        ->and($claim->code_expires_at)->not->toBeNull()
        ->and($claim->status)->toBe(ClaimStatus::Pending);

    Notification::assertSentOnDemand(BusinessClaimCodeNotification::class);
});

it('rejects an email claim whose target is not on the business domain (FR-002-11a)', function () {
    $business = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);
    $claimant = User::factory()->create();

    app(StartBusinessClaim::class)->handle($business, $claimant, ClaimMethod::Email, ['target' => 'jane@gmail.com']);
})->throws(ValidationException::class);

it('rejects an email claim when the business domain is a free email provider (FR-002-12)', function () {
    $business = Business::factory()->create(['primary_domain' => 'gmail.com']);
    $claimant = User::factory()->create();

    app(StartBusinessClaim::class)->handle($business, $claimant, ClaimMethod::Email, ['target' => 'someone@gmail.com']);
})->throws(ValidationException::class);

it('rejects an email or domain-proof claim on a business with no domain', function (ClaimMethod $method) {
    $business = Business::factory()->create(['primary_domain' => null]);
    $claimant = User::factory()->create();

    app(StartBusinessClaim::class)->handle($business, $claimant, $method, ['target' => 'a@b.com']);
})->throws(ValidationException::class)->with([ClaimMethod::Email, ClaimMethod::DnsTxt, ClaimMethod::HtmlFile]);

it('starts a DNS TXT claim with a verification token (FR-002-11b)', function () {
    $business = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);
    $claimant = User::factory()->create();

    $claim = app(StartBusinessClaim::class)->handle($business, $claimant, ClaimMethod::DnsTxt);

    expect($claim->verification_token)->not->toBeEmpty();
});

it('starts a manual claim, scanning and privately storing documents (FR-002-11d)', function () {
    Storage::fake('local');
    $business = Business::factory()->create();
    $claimant = User::factory()->create();

    $claim = app(StartBusinessClaim::class)->handle($business, $claimant, ClaimMethod::Manual, [
        'notes' => 'I run this airline.',
        'documents' => [File::create('certificate.pdf', 10)],
    ]);

    expect($claim->notes)->toBe('I run this airline.')
        ->and($claim->documents)->toHaveCount(1);
    Storage::disk('local')->assertExists($claim->documents[0]);
});

it('rejects a manual claim document that fails the malware scan', function () {
    Storage::fake('local');
    $business = Business::factory()->create();
    $claimant = User::factory()->create();
    app()->instance(MalwareScanner::class, new class implements MalwareScanner
    {
        public function scan(string $filePath): ScanResult
        {
            return new ScanResult(clean: false);
        }
    });

    app(StartBusinessClaim::class)->handle($business, $claimant, ClaimMethod::Manual, [
        'documents' => [File::create('certificate.pdf', 10)],
    ]);
})->throws(ValidationException::class);

// --- VerifyBusinessClaimCode --------------------------------------------

function startEmailClaim(): BusinessClaim
{
    $business = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);
    $claimant = User::factory()->create();

    return app(StartBusinessClaim::class)->handle($business, $claimant, ClaimMethod::Email, ['target' => 'jane@skyhop-travel.com']);
}

it('completes the claim on a correct code: claimant becomes Owner (FR-002-13)', function () {
    $claim = startEmailClaim();

    app(VerifyBusinessClaimCode::class)->handle($claim, $claim->verification_code);

    $business = $claim->business->fresh();
    expect($business->status)->toBe(BusinessStatus::Claimed)
        ->and($business->claimed_at)->not->toBeNull()
        ->and($business->hasBusinessRole($claim->claimant, BusinessRole::Owner))->toBeTrue()
        ->and($claim->fresh()->status)->toBe(ClaimStatus::Approved);
});

it('rejects a wrong code and increments attempts', function () {
    $claim = startEmailClaim();

    try {
        app(VerifyBusinessClaimCode::class)->handle($claim, '000000');
    } catch (ValidationException) {
        // expected
    }

    expect($claim->fresh()->attempts)->toBe(1)
        ->and($claim->business->fresh()->status)->toBe(BusinessStatus::Unclaimed);
});

it('expires the claim after 5 wrong attempts (edge cases table)', function () {
    $claim = startEmailClaim();

    for ($i = 0; $i < 5; $i++) {
        try {
            app(VerifyBusinessClaimCode::class)->handle($claim->fresh(), '000000');
        } catch (ValidationException) {
            // expected
        }
    }

    expect($claim->fresh()->status)->toBe(ClaimStatus::Expired);
});

it('rejects an expired code (edge cases table: 30 minutes)', function () {
    $claim = startEmailClaim();
    $claim->forceFill(['code_expires_at' => now()->subMinute()])->save();

    app(VerifyBusinessClaimCode::class)->handle($claim, $claim->verification_code);
})->throws(ValidationException::class);

it('queues a re-claim for Owner response when the business is already claimed (FR-002-14)', function () {
    Notification::fake();
    $existingOwner = User::factory()->create();
    $business = Business::factory()->claimed()->create(['primary_domain' => 'skyhop-travel.com']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $existingOwner->assignRole(BusinessRole::Owner->value);

    $newClaimant = User::factory()->create();
    $claim = app(StartBusinessClaim::class)->handle($business, $newClaimant, ClaimMethod::Email, ['target' => 'someone@skyhop-travel.com']);

    app(VerifyBusinessClaimCode::class)->handle($claim, $claim->verification_code);

    expect($claim->fresh()->status)->toBe(ClaimStatus::AwaitingOwnerResponse)
        ->and($claim->fresh()->owner_response_deadline)->not->toBeNull()
        ->and($business->hasBusinessRole($newClaimant, BusinessRole::Owner))->toBeFalse();

    Notification::assertSentTo($existingOwner, BusinessReclaimRequestNotification::class);
});

// --- VerifyBusinessDomainClaim -------------------------------------------

it('completes a DNS/HTML claim once the domain checker approves it', function () {
    $business = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);
    $claimant = User::factory()->create();
    $claim = app(StartBusinessClaim::class)->handle($business, $claimant, ClaimMethod::DnsTxt);

    app(VerifyBusinessDomainClaim::class)->handle($claim);

    expect($business->fresh()->status)->toBe(BusinessStatus::Claimed);
});

it('leaves the claim pending when the domain checker has not found it yet', function () {
    $business = Business::factory()->create(['primary_domain' => 'skyhop-travel.com']);
    $claimant = User::factory()->create();
    $claim = app(StartBusinessClaim::class)->handle($business, $claimant, ClaimMethod::DnsTxt);

    app()->instance(DomainClaimChecker::class, new class implements DomainClaimChecker
    {
        public function check(BusinessClaim $claim): bool
        {
            return false;
        }
    });

    app(VerifyBusinessDomainClaim::class)->handle($claim);
})->throws(ValidationException::class);

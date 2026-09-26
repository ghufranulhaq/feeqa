<?php

use App\Actions\Verification\IssueAttestation;
use App\Actions\Verification\RevokeAttestation;
use App\Domain\Staff\StaffRole;
use App\Drivers\Signing\SigningService;
use App\Models\ReviewVerification;
use App\Models\User;
use App\Models\VerificationAttestation;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

function issueTestAttestation(?ReviewVerification $verification = null): VerificationAttestation
{
    $signing = app(SigningService::class);

    if ($signing->publicKeys() === []) {
        $signing->generateKey();
    }

    return app(IssueAttestation::class)->handle($verification ?? ReviewVerification::factory()->create());
}

beforeEach(function () {
    config(['platform.signing.keys_path' => storage_path('framework/testing/signing-'.uniqid().'.json')]);
    app()->forgetInstance(SigningService::class);
});

it('shows a valid, unrevoked attestation (FR-004-15)', function () {
    $attestation = issueTestAttestation();

    $response = $this->get(route('verification.check', $attestation->id));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('public/verification-check')
        ->where('attestation.id', $attestation->id)
        ->where('attestation.signature_valid', true)
        ->where('attestation.revoked', false));
});

it('never exposes the proof fingerprint or the raw JWS on the public check page', function () {
    $verification = ReviewVerification::factory()->create(['proof_fingerprint' => 'super-secret-fp']);
    $attestation = issueTestAttestation($verification);

    $response = $this->get(route('verification.check', $attestation->id));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('public/verification-check')
        ->missing('attestation.jws')
        ->missing('attestation.proof_fingerprint'));

    expect($response->getContent())->not->toContain('super-secret-fp');
});

it('detects a tampered attestation payload (spec 004 §6 acceptance)', function () {
    $attestation = issueTestAttestation();
    $tamperedJws = substr_replace($attestation->jws, $attestation->jws[-1] === 'A' ? 'B' : 'A', -1);
    $attestation->forceFill(['jws' => $tamperedJws])->save();

    $response = $this->get(route('verification.check', $attestation->id));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('public/verification-check')
        ->where('attestation.signature_valid', false));
});

it('shows a revoked attestation as revoked', function () {
    $attestation = issueTestAttestation();
    $staff = User::factory()->create(['staff_role' => StaffRole::Moderator->value]);

    app(RevokeAttestation::class)->handle($attestation, $staff, 'fraud_found');

    $response = $this->get(route('verification.check', $attestation->id));

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('public/verification-check')
        ->where('attestation.revoked', true)
        ->where('attestation.revoked_reason_code', 'fraud_found'));
});

it('404s for an unknown attestation ID with no signal about whether it ever existed', function () {
    $response = $this->get(route('verification.check', (string) Str::uuid()));

    $response->assertNotFound();
});

it('lists revoked attestations publicly with only ID, date, and reason (FR-004-17)', function () {
    $attestation = issueTestAttestation();
    $staff = User::factory()->create(['staff_role' => StaffRole::Moderator->value]);
    app(RevokeAttestation::class)->handle($attestation, $staff, 'fraud_found');

    issueTestAttestation();

    $response = $this->get(route('verification.revocations'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('public/verification-revocations')
        ->has('revocations', 1)
        ->where('revocations.0.id', $attestation->id)
        ->where('revocations.0.revoked_reason_code', 'fraud_found'));
});

it('publishes public signing keys at the well-known path (FR-004-16)', function () {
    $signing = app(SigningService::class);
    $kid = $signing->generateKey();

    $response = $this->get('/.well-known/platform-keys.json');

    $response->assertOk()
        ->assertJsonPath('keys.0.kid', $kid)
        ->assertJsonMissingPath('keys.0.secret_key');
});

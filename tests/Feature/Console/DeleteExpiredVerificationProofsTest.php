<?php

use App\Models\ReviewVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function verificationWithProof(int $decidedDaysAgo): ReviewVerification
{
    $path = 'verification-proofs/'.uniqid().'/receipt.pdf';
    Storage::disk('local')->put($path, 'fake proof bytes');

    return ReviewVerification::factory()->approved()->create([
        'decided_at' => now()->subDays($decidedDaysAgo),
        'extracted_fields' => ['merchant' => 'Acme Air', 'date' => '2026-01-01'],
        'proof_paths' => [$path],
    ]);
}

beforeEach(function () {
    Storage::fake('local');
});

it('deletes proof files and extracted fields 30 days after the decision (FR-004-23)', function () {
    $verification = verificationWithProof(31);
    $path = $verification->proof_paths[0];

    $this->artisan('verification:delete-expired-proofs')->assertExitCode(0);

    Storage::disk('local')->assertMissing($path);
    expect($verification->fresh())
        ->proof_paths->toBeNull()
        ->extracted_fields->toBeNull()
        ->proof_deleted_at->not->toBeNull();
});

it('leaves a verification decided less than 30 days ago untouched', function () {
    $verification = verificationWithProof(29);
    $path = $verification->proof_paths[0];

    $this->artisan('verification:delete-expired-proofs');

    Storage::disk('local')->assertExists($path);
    expect($verification->fresh())->proof_paths->not->toBeNull();
});

it('leaves a still-pending verification (no decision yet) untouched', function () {
    $verification = ReviewVerification::factory()->create([
        'extracted_fields' => ['merchant' => 'Acme Air'],
    ]);

    $this->artisan('verification:delete-expired-proofs');

    expect($verification->fresh()->extracted_fields)->not->toBeNull();
});

it('is a no-op in demo, per the demo relaxation (constitution §5.6)', function () {
    app()['env'] = 'demo';
    config(['platform.verification.delete_raw_proof_files_on_schedule' => false]);
    $verification = verificationWithProof(31);
    $path = $verification->proof_paths[0];

    $this->artisan('verification:delete-expired-proofs');

    Storage::disk('local')->assertExists($path);
    expect($verification->fresh()->proof_paths)->not->toBeNull();
});

it('always deletes in production, whatever the config says (constitution §5.6)', function () {
    app()['env'] = 'production';
    config(['platform.verification.delete_raw_proof_files_on_schedule' => false]);
    $verification = verificationWithProof(31);
    $path = $verification->proof_paths[0];

    $this->artisan('verification:delete-expired-proofs');

    Storage::disk('local')->assertMissing($path);
    expect($verification->fresh()->proof_paths)->toBeNull();
});

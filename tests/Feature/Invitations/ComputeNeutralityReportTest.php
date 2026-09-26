<?php

use App\Actions\Invitations\ComputeNeutralityReport;
use App\Domain\Invitations\InvitationMethod;
use App\Domain\Reviews\SourceLabel;
use App\Domain\Verification\TransactionRecordHash;
use App\Models\Business;
use App\Models\BusinessTransactionRecord;
use App\Models\Review;
use App\Models\ReviewInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('groups invitations created in the last 30 days by method (FR-005-18)', function () {
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->count(2)->create(['method' => InvitationMethod::Manual, 'created_at' => now()]);
    ReviewInvitation::factory()->for($business)->create(['method' => InvitationMethod::Csv, 'created_at' => now()]);
    ReviewInvitation::factory()->for($business)->create(['method' => InvitationMethod::Manual, 'created_at' => now()->subDays(40)]);

    $report = (new ComputeNeutralityReport)->handle($business);

    expect($report->invitationsPerMethod)->toBe(['manual' => 2, 'csv' => 1])
        ->and($report->totalInvitations)->toBe(3);
});

it('leaves the cancellation rate null below the 50-invitation gate', function () {
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->count(10)->create(['created_at' => now()]);
    ReviewInvitation::factory()->for($business)->count(5)->cancelled()->create(['created_at' => now()]);

    $report = (new ComputeNeutralityReport)->handle($business);

    expect($report->cancellationRate)->toBeNull();
});

it('flags a business whose cancellation rate exceeds 20% over 30 days with >= 50 invitations (FR-005-18)', function () {
    Log::spy();
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->count(30)->create(['created_at' => now()]);
    ReviewInvitation::factory()->for($business)->count(20)->cancelled()->create(['created_at' => now()]);

    $report = (new ComputeNeutralityReport)->handle($business);

    expect($report->cancellationRate)->toBe(0.4);
    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'cancellation rate exceeds 20%'))
        ->once();
});

it('does not flag a cancellation rate at or below 20%', function () {
    Log::spy();
    $business = Business::factory()->create();
    ReviewInvitation::factory()->for($business)->count(40)->create(['created_at' => now()]);
    ReviewInvitation::factory()->for($business)->count(10)->cancelled()->create(['created_at' => now()]);

    (new ComputeNeutralityReport)->handle($business);

    Log::shouldNotHaveReceived('warning');
});

it('leaves the transactions-invited percentage null with no transaction data (FR-005-18)', function () {
    $business = Business::factory()->create();

    $report = (new ComputeNeutralityReport)->handle($business);

    expect($report->transactionsInvitedPercentage)->toBeNull();
});

it('computes the percentage of known transactions matched to an invitation by reference', function () {
    $business = Business::factory()->create();
    BusinessTransactionRecord::factory()->for($business)->count(4)->create();
    BusinessTransactionRecord::factory()->for($business)->create(['reference_hash' => TransactionRecordHash::reference('ORDER-1')]);
    ReviewInvitation::factory()->for($business)->create(['reference' => 'ORDER-1']);

    $report = (new ComputeNeutralityReport)->handle($business);

    expect($report->transactionsInvitedPercentage)->toBe(0.2);
});

it('averages invited and organic review ratings separately', function () {
    $business = Business::factory()->create();
    Review::factory()->for($business)->create(['source_label' => SourceLabel::Invited, 'star_rating' => 5]);
    Review::factory()->for($business)->create(['source_label' => SourceLabel::Invited, 'star_rating' => 4]);
    Review::factory()->for($business)->create(['source_label' => SourceLabel::Organic, 'star_rating' => 2]);

    $report = (new ComputeNeutralityReport)->handle($business);

    expect($report->invitedAverageRating)->toBe(4.5)
        ->and($report->invitedReviewCount)->toBe(2)
        ->and($report->organicAverageRating)->toBe(2.0)
        ->and($report->organicReviewCount)->toBe(1);
});

it('flags a >1.5-star invited/organic rating gap only with >= 50 reviews each and < 50% of transactions invited (FR-005-18)', function () {
    Log::spy();
    $business = Business::factory()->create();
    BusinessTransactionRecord::factory()->for($business)->count(100)->create();
    BusinessTransactionRecord::factory()->for($business)->create(['reference_hash' => TransactionRecordHash::reference('ORDER-1')]);
    ReviewInvitation::factory()->for($business)->create(['reference' => 'ORDER-1']);
    Review::factory()->for($business)->count(50)->create(['source_label' => SourceLabel::Invited, 'star_rating' => 5]);
    Review::factory()->for($business)->count(50)->create(['source_label' => SourceLabel::Organic, 'star_rating' => 3]);

    (new ComputeNeutralityReport)->handle($business);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'low invite coverage'))
        ->once();
});

it('does not flag a large rating gap when most known transactions were invited', function () {
    Log::spy();
    $business = Business::factory()->create();
    BusinessTransactionRecord::factory()->for($business)->count(1)->create();
    BusinessTransactionRecord::factory()->for($business)->create(['reference_hash' => TransactionRecordHash::reference('ORDER-1')]);
    ReviewInvitation::factory()->for($business)->create(['reference' => 'ORDER-1']);
    Review::factory()->for($business)->count(50)->create(['source_label' => SourceLabel::Invited, 'star_rating' => 5]);
    Review::factory()->for($business)->count(50)->create(['source_label' => SourceLabel::Organic, 'star_rating' => 3]);

    (new ComputeNeutralityReport)->handle($business);

    Log::shouldNotHaveReceived('warning');
});

it('does not flag a rating gap with no transaction data at all, even if it exceeds 1.5 stars', function () {
    Log::spy();
    $business = Business::factory()->create();
    Review::factory()->for($business)->count(50)->create(['source_label' => SourceLabel::Invited, 'star_rating' => 5]);
    Review::factory()->for($business)->count(50)->create(['source_label' => SourceLabel::Organic, 'star_rating' => 3]);

    (new ComputeNeutralityReport)->handle($business);

    Log::shouldNotHaveReceived('warning');
});

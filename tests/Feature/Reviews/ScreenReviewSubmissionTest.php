<?php

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Domain\Reviews\ReviewStatus;
use App\Models\Business;
use App\Models\Review;
use App\Models\ScreeningRuleState;
use App\Models\User;

it('publishes clean text (FR-003-11)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    $outcome = (new ScreenReviewSubmission)->handle(
        $reviewer,
        $business,
        'Great service',
        'Everything about this trip went smoothly from start to finish.',
    );

    expect($outcome->status)->toBe(ReviewStatus::Published)
        ->and($outcome->reason)->toBeNull();
});

it('rejects text containing a blocklisted word with a reason (FR-003-11)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    $outcome = (new ScreenReviewSubmission)->handle(
        $reviewer,
        $business,
        'This is bullshit',
        'What a load of bullshit this whole experience was.',
    );

    expect($outcome->status)->toBe(ReviewStatus::Rejected)
        ->and($outcome->reason)->not->toBeNull();
});

it('rejects a title containing a blocklisted word', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    $outcome = (new ScreenReviewSubmission)->handle(
        $reviewer,
        $business,
        'These people are a bunch of assholes',
        'Everything about this trip went smoothly from start to finish.',
    );

    expect($outcome->status)->toBe(ReviewStatus::Rejected);
});

it('holds text near-identical to the same author\'s text on a different business within 30 days (edge case: same text on several businesses)', function () {
    $reviewer = User::factory()->create();
    $earlierBusiness = Business::factory()->create();
    $newBusiness = Business::factory()->create();
    $text = 'Everything about this trip went smoothly from start to finish, no complaints at all.';

    Review::factory()->for($earlierBusiness)->create([
        'reviewer_id' => $reviewer->id,
        'text' => $text,
        'created_at' => now()->subDays(5),
    ]);

    $outcome = (new ScreenReviewSubmission)->handle($reviewer, $newBusiness, 'Great trip', $text);

    expect($outcome->status)->toBe(ReviewStatus::Held)
        ->and($outcome->reason)->not->toBeNull();
});

it('does not hold near-identical text on the same business (that is a different, duplicate-submission rule)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();
    $text = 'Everything about this trip went smoothly from start to finish, no complaints at all.';

    Review::factory()->for($business)->create([
        'reviewer_id' => $reviewer->id,
        'text' => $text,
        'created_at' => now()->subDays(5),
    ]);

    $outcome = (new ScreenReviewSubmission)->handle($reviewer, $business, 'Great trip', $text);

    expect($outcome->status)->toBe(ReviewStatus::Published);
});

it('does not hold near-identical text posted more than 30 days ago', function () {
    $reviewer = User::factory()->create();
    $earlierBusiness = Business::factory()->create();
    $newBusiness = Business::factory()->create();
    $text = 'Everything about this trip went smoothly from start to finish, no complaints at all.';

    Review::factory()->for($earlierBusiness)->create([
        'reviewer_id' => $reviewer->id,
        'text' => $text,
        'created_at' => now()->subDays(31),
    ]);

    $outcome = (new ScreenReviewSubmission)->handle($reviewer, $newBusiness, 'Great trip', $text);

    expect($outcome->status)->toBe(ReviewStatus::Published);
});

it('does not hold clearly different text on a different business', function () {
    $reviewer = User::factory()->create();
    $earlierBusiness = Business::factory()->create();
    $newBusiness = Business::factory()->create();

    Review::factory()->for($earlierBusiness)->create([
        'reviewer_id' => $reviewer->id,
        'text' => 'The hotel room was spotless and the staff were incredibly friendly throughout our stay.',
        'created_at' => now()->subDays(5),
    ]);

    $outcome = (new ScreenReviewSubmission)->handle(
        $reviewer,
        $newBusiness,
        'Flight delayed',
        'Our flight was delayed by six hours and nobody at the gate could explain why.',
    );

    expect($outcome->status)->toBe(ReviewStatus::Published);
});

it('holds near-identical (not exact) text posted by three distinct reviewers about the same business within an hour (User Scenario 1, FR-006-04)', function () {
    $business = Business::factory()->create();
    $text = 'This flight was amazing and the crew treated us like royalty the whole time.';

    // 92%-similar, not identical, so this exercises the near-identical
    // hold rule rather than the exact-duplicate auto-reject rule.
    Review::factory()->for($business)->create(['text' => $text.' Loved it!', 'created_at' => now()->subMinutes(10)]);
    Review::factory()->for($business)->create(['text' => $text.' Amazing.', 'created_at' => now()->subMinutes(20)]);

    $newReviewer = User::factory()->create();
    $outcome = (new ScreenReviewSubmission)->handle($newReviewer, $business, 'Great', $text);

    expect($outcome->status)->toBe(ReviewStatus::Held)
        ->and($outcome->triggeredRules)->toContain('coordinated_duplicate_cluster');
});

it('does not hold a coordinated cluster with only two distinct reviewers (below the FR-006-04 threshold)', function () {
    $business = Business::factory()->create();
    $text = 'This flight was amazing and the crew treated us like royalty the whole time.';

    Review::factory()->for($business)->create(['text' => $text.' Loved it!', 'created_at' => now()->subMinutes(10)]);

    $newReviewer = User::factory()->create();
    $outcome = (new ScreenReviewSubmission)->handle($newReviewer, $business, 'Great', $text);

    expect($outcome->status)->toBe(ReviewStatus::Published);
});

it('auto-rejects exact duplicate text shared by three distinct accounts (FR-006-05)', function () {
    $text = 'Never flying with them again, lost my luggage and nobody helped.';

    Review::factory()->create(['text' => $text, 'created_at' => now()->subHours(2)]);
    Review::factory()->create(['text' => $text, 'created_at' => now()->subHours(5)]);

    $newReviewer = User::factory()->create();
    $business = Business::factory()->create();
    $outcome = (new ScreenReviewSubmission)->handle($newReviewer, $business, 'Terrible', $text);

    expect($outcome->status)->toBe(ReviewStatus::Rejected)
        ->and($outcome->triggeredRules)->toContain('exact_duplicate_cluster');
});

it('holds, rather than rejects, an exact duplicate cluster once the rule is disabled (FR-006-05, FR-006-20)', function () {
    ScreeningRuleState::disable('exact_duplicate_cluster', 'precision fell below 99%');
    $text = 'Never flying with them again, lost my luggage and nobody helped.';

    Review::factory()->create(['text' => $text, 'created_at' => now()->subHours(2)]);
    Review::factory()->create(['text' => $text, 'created_at' => now()->subHours(5)]);

    $newReviewer = User::factory()->create();
    $business = Business::factory()->create();
    $outcome = (new ScreenReviewSubmission)->handle($newReviewer, $business, 'Terrible', $text);

    expect($outcome->status)->toBe(ReviewStatus::Held);
});

it('holds text containing an email address as personal information (FR-006-02, FR-006-04)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    $outcome = (new ScreenReviewSubmission)->handle(
        $reviewer,
        $business,
        'Contact them directly',
        'The manager was rude, email him yourself at john.smith@example.com and see.',
    );

    expect($outcome->status)->toBe(ReviewStatus::Held)
        ->and($outcome->triggeredRules)->toContain('personal_info_detected');
});

it('carries a non-zero risk score and computed signals for a submission with mild signals', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    $outcome = (new ScreenReviewSubmission)->handle(
        $reviewer,
        $business,
        'Great',
        'They gave me a discount for leaving this review, but the flight itself was fine.',
    );

    expect($outcome->triggeredRules)->toContain('incentive_language')
        ->and($outcome->riskScore)->toBeGreaterThan(0.0)
        ->and($outcome->signals['incentive_language'])->toBeTrue();
});

it('flags a submission from a known-bad IP range as network_reputation (FR-006-04)', function () {
    config(['platform.moderation.network.known_bad_ranges' => ['203.0.113.0/24']]);
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    $outcome = (new ScreenReviewSubmission)->handle(
        $reviewer,
        $business,
        'Great',
        'Everything about this trip went smoothly from start to finish.',
        '203.0.113.42',
    );

    expect($outcome->triggeredRules)->toContain('network_reputation')
        ->and($outcome->signals['network_flagged'])->toBeTrue();
});

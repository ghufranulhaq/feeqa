<?php

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Domain\Reviews\ReviewStatus;
use App\Models\Business;
use App\Models\Review;
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

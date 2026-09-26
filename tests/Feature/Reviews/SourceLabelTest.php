<?php

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Actions\Reviews\SubmitReview;
use App\Domain\Reviews\SourceLabel;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     star_rating: int, title: string, text: string,
 *     date_of_experience: string, confirmed_genuine: bool,
 * }
 */
function reviewSubmissionData(array $overrides = []): array
{
    return array_merge([
        'star_rating' => 5,
        'title' => 'A genuinely good experience',
        'text' => 'The flight left on time and the crew were friendly throughout the whole journey.',
        'date_of_experience' => now()->subDay()->toDateString(),
        'confirmed_genuine' => true,
    ], $overrides);
}

/**
 * FR-003-14, FR-003-15: only two source labels are reachable today —
 * `Invited` and `Redirected` both need the invitation/generic-link system
 * (005), which doesn't exist yet.
 */
it('sets every submitted review to Organic, the only source reachable before 005 exists', function () {
    $business = Business::factory()->create();
    $reviewer = User::factory()->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))
        ->handle($reviewer, $business, reviewSubmissionData());

    expect($review->source_label)->toBe(SourceLabel::Organic);
});

/**
 * FR-003-15: "It cannot be edited by anyone." The action's data shape
 * doesn't accept a `source_label` key at all, but a caller could still
 * pass one in the raw array (a form field renamed by a malicious client,
 * for instance) — it must have no effect.
 */
it('ignores a source_label smuggled into the submission data', function () {
    $business = Business::factory()->create();
    $reviewer = User::factory()->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))
        ->handle($reviewer, $business, reviewSubmissionData(['source_label' => SourceLabel::Invited->value]));

    expect($review->source_label)->toBe(SourceLabel::Organic);
});

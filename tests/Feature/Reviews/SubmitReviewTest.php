<?php

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Actions\Reviews\SubmitReview;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Invitations\InvitationMethod;
use App\Domain\Invitations\InvitationStatus;
use App\Domain\Reviews\ReviewStatus;
use App\Domain\Reviews\SourceLabel;
use App\Domain\Verification\VerificationMethod;
use App\Drivers\Signing\SigningService;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewInvitation;
use App\Models\User;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function memberOfBusiness(Business $business, BusinessRole $role): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @return array{
 *     star_rating: int, title: string, text: string,
 *     date_of_experience: string, confirmed_genuine: bool, idempotency_key?: string,
 * }
 */
function validReviewData(array $overrides = []): array
{
    return [
        ...[
            'star_rating' => 5,
            'title' => 'Great trip overall',
            'text' => 'Everything about this trip went smoothly from start to finish, no complaints at all.',
            'date_of_experience' => now()->subDays(3)->toDateString(),
            'confirmed_genuine' => true,
            'idempotency_key' => (string) Str::uuid(),
        ],
        ...$overrides,
    ];
}

it('publishes a clean review with Organic source label and en language (FR-003-09)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, validReviewData());

    expect($review->status)->toBe(ReviewStatus::Published)
        ->and($review->source_label)->toBe(SourceLabel::Organic)
        ->and($review->language)->toBe('en')
        ->and($review->confirmed_genuine)->toBeTrue()
        ->and($review->published_at)->not->toBeNull();
});

it('rejects when the confirmation checkbox is not true (FR-003-06)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(['confirmed_genuine' => false]),
    );
})->throws(ValidationException::class);

it('rejects a star rating outside 1-5 (FR-003-02 edge case: rating missing or out of range)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(['star_rating' => 6]),
    );
})->throws(ValidationException::class);

it('rejects a title shorter than 5 characters (FR-003-02)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(['title' => 'Hi']),
    );
})->throws(ValidationException::class);

it('rejects text under 30 non-whitespace characters even when emoji pad the length (FR-003-02 edge case)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(['text' => 'Bad. '.str_repeat('😀 ', 20)]),
    );
})->throws(ValidationException::class);

it('rejects text over 5,000 characters (FR-003-02 edge case)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(['text' => str_repeat('a', 5001)]),
    );
})->throws(ValidationException::class);

it('rejects a future date of experience (FR-003-04)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(['date_of_experience' => now()->addDay()->toDateString()]),
    );
})->throws(ValidationException::class);

it('rejects a date of experience more than 12 months ago (FR-003-04)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(['date_of_experience' => now()->subMonths(13)->toDateString()]),
    );
})->throws(ValidationException::class);

it('blocks a business member from submitting a review of that business (FR-003-07)', function () {
    $business = Business::factory()->claimed()->create();
    $member = memberOfBusiness($business, BusinessRole::Owner);

    (new SubmitReview(new ScreenReviewSubmission))->handle($member, $business, validReviewData());
})->throws(ValidationException::class);

it('allows a review of a business closed less than 12 months ago (edge cases table)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create(['status' => 'closed', 'closed_at' => now()->subMonths(6)]);

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, validReviewData());

    expect($review->business_id)->toBe($business->id);
});

it('rejects a review of a business closed more than 12 months ago (edge cases table)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create(['status' => 'closed', 'closed_at' => now()->subMonths(13)]);

    (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, validReviewData());
})->throws(ValidationException::class);

it('rejects a second review of the same business within 30 days (FR-003-08 edge case: duplicate submission)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    Review::factory()->for($business)->create([
        'reviewer_id' => $reviewer->id,
        'created_at' => now()->subDays(10),
    ]);

    (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, validReviewData());
})->throws(ValidationException::class);

it('allows a new review of the same business after 30 days (FR-003-08)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    Review::factory()->for($business)->create([
        'reviewer_id' => $reviewer->id,
        'created_at' => now()->subDays(31),
    ]);

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, validReviewData());

    expect($review->status)->toBe(ReviewStatus::Published);
});

it('returns the existing review instead of creating a second one for a repeated idempotency key (edge case: double-post prevention)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();
    $data = validReviewData();

    $first = (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, $data);
    $second = (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, $data);

    expect($second->id)->toBe($first->id)
        ->and(Review::count())->toBe(1);
});

it('is held, not published, when the screening step flags the text (integration with T2)', function () {
    $reviewer = User::factory()->create();
    $earlierBusiness = Business::factory()->create();
    $newBusiness = Business::factory()->create();
    $text = 'Everything about this trip went smoothly from start to finish, no complaints at all.';

    Review::factory()->for($earlierBusiness)->create([
        'reviewer_id' => $reviewer->id,
        'text' => $text,
        'created_at' => now()->subDays(5),
    ]);

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $newBusiness,
        validReviewData(['text' => $text]),
    );

    expect($review->status)->toBe(ReviewStatus::Held)
        ->and($review->published_at)->toBeNull();
});

it('sets source_label Invited and marks the invitation reviewed for a non-link method (FR-005-02)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();
    $invitation = ReviewInvitation::factory()->for($business)->create(['method' => InvitationMethod::Manual]);

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(),
        invitationToken: $invitation->token,
    );

    expect($review->source_label)->toBe(SourceLabel::Invited)
        ->and($invitation->fresh()->status)->toBe(InvitationStatus::Reviewed)
        ->and($invitation->fresh()->review_id)->toBe($review->id)
        ->and($invitation->fresh()->reviewed_at)->not->toBeNull();
});

it('sets source_label Redirected for the link method (FR-005-02)', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();
    $invitation = ReviewInvitation::factory()->for($business)->link()->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(),
        invitationToken: $invitation->token,
    );

    expect($review->source_label)->toBe(SourceLabel::Redirected);
});

it('issues a transaction_invitation attestation for a transaction-linked method carrying a reference (FR-005-02)', function () {
    app(SigningService::class)->generateKey();
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();
    $invitation = ReviewInvitation::factory()->for($business)->api()->withReference('BK-777888')->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(),
        invitationToken: $invitation->token,
    );

    expect($review->fresh()->isVerified())->toBeTrue()
        ->and($review->verifications()->first()->method)->toBe(VerificationMethod::TransactionInvitation);
});

it('does not issue an attestation for a non-transaction-linked method, even with a reference', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();
    $invitation = ReviewInvitation::factory()->for($business)->withReference('BK-777999')->create(['method' => InvitationMethod::Manual]);

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewData(),
        invitationToken: $invitation->token,
    );

    expect($review->fresh()->isVerified())->toBeFalse();
});

it('rejects reusing the same reference across two different invitations (FR-004-11 anti-reuse)', function () {
    app(SigningService::class)->generateKey();
    $reviewerA = User::factory()->create();
    $reviewerB = User::factory()->create();
    $business = Business::factory()->create();
    $firstInvitation = ReviewInvitation::factory()->for($business)->api()->withReference('BK-SAME-001')->create();
    $secondInvitation = ReviewInvitation::factory()->for($business)->api()->withReference('BK-SAME-001')->create();

    $firstReview = (new SubmitReview(new ScreenReviewSubmission))->handle($reviewerA, $business, validReviewData(), invitationToken: $firstInvitation->token);
    $secondReview = (new SubmitReview(new ScreenReviewSubmission))->handle($reviewerB, $business, validReviewData(['idempotency_key' => Str::uuid()->toString()]), invitationToken: $secondInvitation->token);

    expect($firstReview->fresh()->isVerified())->toBeTrue()
        ->and($secondReview->fresh()->isVerified())->toBeFalse();
});

it('rejects an unknown invitation token', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, validReviewData(), invitationToken: 'not-a-real-token');
})->throws(ValidationException::class);

it('rejects an invitation token for a different business', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $invitation = ReviewInvitation::factory()->for($otherBusiness)->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, validReviewData(), invitationToken: $invitation->token);
})->throws(ValidationException::class);

it('rejects an already-used invitation token', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();
    $invitation = ReviewInvitation::factory()->for($business)->reviewed()->create(['review_id' => Review::factory()->for($business)->create()->id]);

    (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, validReviewData(), invitationToken: $invitation->token);
})->throws(ValidationException::class);

it('rejects an expired invitation token', function () {
    $reviewer = User::factory()->create();
    $business = Business::factory()->create();
    $invitation = ReviewInvitation::factory()->for($business)->expired()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle($reviewer, $business, validReviewData(), invitationToken: $invitation->token);
})->throws(ValidationException::class);

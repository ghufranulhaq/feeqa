<?php

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Actions\Reviews\SubmitReview;
use App\Actions\Reviews\UpdateReview;
use App\Domain\Businesses\BusinessRole;
use App\Domain\Reviews\ReviewStatus;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewTaggedNotification;
use Database\Seeders\Base\BusinessRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(BusinessRolesSeeder::class);
});

function memberOfTaggedBusiness(Business $business, BusinessRole $role = BusinessRole::Owner): User
{
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($business->id);
    $user->assignRole($role->value);

    return $user;
}

/**
 * @return array{
 *     star_rating: int, title: string, text: string,
 *     date_of_experience: string, confirmed_genuine: bool,
 * }
 */
function validTaggedReviewData(array $overrides = []): array
{
    return array_merge([
        'star_rating' => 5,
        'title' => 'Smooth booking through the agency',
        'text' => 'The agency sorted out a seat change with the airline within minutes of my call.',
        'date_of_experience' => now()->subDays(3)->toDateString(),
        'confirmed_genuine' => true,
    ], $overrides);
}

it('stores the tagged business on submission (FR-003-31)', function () {
    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $reviewer = User::factory()->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validTaggedReviewData(['tagged_business_ids' => [$taggedBusiness->id]]),
    );

    expect($review->tagged_business_id)->toBe($taggedBusiness->id);
});

it('rejects tagging the business being reviewed (edge case: self-tag)', function () {
    $business = Business::factory()->create();
    $reviewer = User::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validTaggedReviewData(['tagged_business_ids' => [$business->id]]),
    );
})->throws(ValidationException::class);

it('rejects tagging more than one business (edge case: multiple tags)', function () {
    $business = Business::factory()->create();
    $first = Business::factory()->create();
    $second = Business::factory()->create();
    $reviewer = User::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validTaggedReviewData(['tagged_business_ids' => [$first->id, $second->id]]),
    );
})->throws(ValidationException::class);

it('rejects tagging a business that does not exist', function () {
    $business = Business::factory()->create();
    $reviewer = User::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validTaggedReviewData(['tagged_business_ids' => [999999]]),
    );
})->throws(ValidationException::class);

it('shows a published tagged review under the tagged business\'s mentions, marked as a review of the other business (FR-003-32)', function () {
    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $reviewer = User::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validTaggedReviewData(['tagged_business_ids' => [$taggedBusiness->id]]),
    );

    $mentions = $taggedBusiness->mentions();

    expect($mentions)->toHaveCount(1)
        ->and($mentions->first()->business_id)->toBe($business->id);
});

it('never adds a tagged review to the tagged business\'s own reviews (FR-003-32 zero-score-effect clause)', function () {
    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $reviewer = User::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validTaggedReviewData(['tagged_business_ids' => [$taggedBusiness->id]]),
    );

    expect($taggedBusiness->reviews()->count())->toBe(0);
});

it('notifies every member of the tagged business when the review publishes (FR-003-32)', function () {
    Notification::fake();

    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $reviewer = User::factory()->create();
    $owner = memberOfTaggedBusiness($taggedBusiness, BusinessRole::Owner);
    $responder = memberOfTaggedBusiness($taggedBusiness, BusinessRole::Responder);

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validTaggedReviewData(['tagged_business_ids' => [$taggedBusiness->id]]),
    );

    Notification::assertSentTo($owner, ReviewTaggedNotification::class);
    Notification::assertSentTo($responder, ReviewTaggedNotification::class);
});

it('does not notify when the tagged review is held instead of published', function () {
    Notification::fake();

    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $reviewer = User::factory()->create();
    $owner = memberOfTaggedBusiness($taggedBusiness);
    $earlierBusiness = Business::factory()->create();
    $text = 'This experience was smooth from start to finish with no issues at all to report.';

    Review::factory()->for($earlierBusiness)->create([
        'reviewer_id' => $reviewer->id,
        'text' => $text,
        'created_at' => now()->subDays(5),
    ]);

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validTaggedReviewData(['text' => $text, 'tagged_business_ids' => [$taggedBusiness->id]]),
    );

    expect($review->status)->toBe(ReviewStatus::Held)
        ->and($review->tagged_business_id)->toBe($taggedBusiness->id);

    Notification::assertNothingSent();
});

it('lets the author add a tag on edit that had none before (FR-003-33)', function () {
    Notification::fake();

    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $author = User::factory()->create();
    $owner = memberOfTaggedBusiness($taggedBusiness);
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    $updated = (new UpdateReview(new ScreenReviewSubmission))->handle(
        $author,
        $review,
        validTaggedReviewData(['tagged_business_ids' => [$taggedBusiness->id]]),
    );

    expect($updated->tagged_business_id)->toBe($taggedBusiness->id);
    Notification::assertSentTo($owner, ReviewTaggedNotification::class);
});

it('lets the author change the tag to a different business on edit (FR-003-33)', function () {
    $business = Business::factory()->create();
    $originalTagged = Business::factory()->create();
    $newTagged = Business::factory()->create();
    $author = User::factory()->create();
    $originalOwner = memberOfTaggedBusiness($originalTagged);
    $newOwner = memberOfTaggedBusiness($newTagged);
    $review = Review::factory()->for($business)->create([
        'reviewer_id' => $author->id,
        'tagged_business_id' => $originalTagged->id,
    ]);

    Notification::fake();

    $updated = (new UpdateReview(new ScreenReviewSubmission))->handle(
        $author,
        $review,
        validTaggedReviewData(['tagged_business_ids' => [$newTagged->id]]),
    );

    expect($updated->tagged_business_id)->toBe($newTagged->id);
    Notification::assertSentTo($newOwner, ReviewTaggedNotification::class);
    Notification::assertNotSentTo($originalOwner, ReviewTaggedNotification::class);
});

it('lets the author remove the tag on edit (FR-003-33)', function () {
    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create([
        'reviewer_id' => $author->id,
        'tagged_business_id' => $taggedBusiness->id,
    ]);

    Notification::fake();

    $updated = (new UpdateReview(new ScreenReviewSubmission))->handle(
        $author,
        $review,
        validTaggedReviewData(),
    );

    expect($updated->tagged_business_id)->toBeNull();
    Notification::assertNothingSent();
});

it('does not re-notify when an edit leaves the tag unchanged', function () {
    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $author = User::factory()->create();
    $owner = memberOfTaggedBusiness($taggedBusiness);
    $review = Review::factory()->for($business)->create([
        'reviewer_id' => $author->id,
        'tagged_business_id' => $taggedBusiness->id,
    ]);

    Notification::fake();

    (new UpdateReview(new ScreenReviewSubmission))->handle(
        $author,
        $review,
        validTaggedReviewData(['tagged_business_ids' => [$taggedBusiness->id], 'title' => 'Updated title for this trip']),
    );

    Notification::assertNothingSent();
});

it('rejects tagging the reviewed business on edit too', function () {
    $business = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    (new UpdateReview(new ScreenReviewSubmission))->handle(
        $author,
        $review,
        validTaggedReviewData(['tagged_business_ids' => [$business->id]]),
    );
})->throws(ValidationException::class);

it('lets the author tag a second business over HTTP and notifies its members', function () {
    Notification::fake();

    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $author = User::factory()->create();
    $owner = memberOfTaggedBusiness($taggedBusiness);
    $review = Review::factory()->for($business)->create(['reviewer_id' => $author->id]);

    $this->actingAs($author)
        ->patch(route('reviews.update', $review), [
            'star_rating' => 4,
            'title' => 'Updated after the agency stepped in',
            'text' => 'The agency sorted out a seat change with the airline within minutes of my call.',
            'date_of_experience' => now()->subDays(3)->toDateString(),
            'tagged_business_id' => $taggedBusiness->id,
        ])
        ->assertRedirect();

    expect($review->fresh()->tagged_business_id)->toBe($taggedBusiness->id);
    Notification::assertSentTo($owner, ReviewTaggedNotification::class);
});

it('clears an existing tag over HTTP when the field is omitted from the edit', function () {
    $business = Business::factory()->create();
    $taggedBusiness = Business::factory()->create();
    $author = User::factory()->create();
    $review = Review::factory()->for($business)->create([
        'reviewer_id' => $author->id,
        'tagged_business_id' => $taggedBusiness->id,
    ]);

    $this->actingAs($author)
        ->patch(route('reviews.update', $review), [
            'star_rating' => 4,
            'title' => 'Updated after the agency stepped in',
            'text' => 'The agency sorted out a seat change with the airline within minutes of my call.',
            'date_of_experience' => now()->subDays(3)->toDateString(),
        ])
        ->assertRedirect();

    expect($review->fresh()->tagged_business_id)->toBeNull();
});

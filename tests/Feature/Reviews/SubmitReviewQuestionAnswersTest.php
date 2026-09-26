<?php

use App\Actions\Reviews\ScreenReviewSubmission;
use App\Actions\Reviews\SubmitReview;
use App\Models\Business;
use App\Models\Category;
use App\Models\CategoryQuestionSet;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     star_rating: int, title: string, text: string,
 *     date_of_experience: string, confirmed_genuine: bool, idempotency_key?: string,
 *     answers?: array<string, mixed>,
 * }
 */
function validReviewDataWithAnswers(array $overrides = []): array
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

function categoryWithQuestionSet(): Category
{
    $category = Category::factory()->create();

    $questionSet = CategoryQuestionSet::create([
        'category_id' => $category->id,
        'version' => 1,
        'published_at' => now(),
    ]);

    $questionSet->questions()->create([
        'key' => 'cleanliness',
        'label' => ['en-GB' => 'How clean was it?'],
        'type' => 'rating_1_5',
        'required' => true,
        'order' => 0,
    ]);

    $questionSet->questions()->create([
        'key' => 'noise_level',
        'label' => ['en-GB' => 'Was it noisy?'],
        'type' => 'yes_no',
        'required' => false,
        'order' => 1,
    ]);

    return $category;
}

it('stores answers with the question set version when all required questions are answered (FR-003-05)', function () {
    $category = categoryWithQuestionSet();
    $business = Business::factory()->create(['primary_category_id' => $category->id]);
    $reviewer = User::factory()->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewDataWithAnswers(['answers' => ['cleanliness' => 4]]),
    );

    expect($review->question_set_version)->toBe(1)
        ->and($review->answers)->toBe(['cleanliness' => 4]);
});

it('allows an optional question to be skipped (FR-003-05)', function () {
    $category = categoryWithQuestionSet();
    $business = Business::factory()->create(['primary_category_id' => $category->id]);
    $reviewer = User::factory()->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewDataWithAnswers(['answers' => ['cleanliness' => 4]]),
    );

    expect($review->answers)->not->toHaveKey('noise_level');
});

it('rejects submission when a required question is left unanswered (FR-003-05)', function () {
    $category = categoryWithQuestionSet();
    $business = Business::factory()->create(['primary_category_id' => $category->id]);
    $reviewer = User::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewDataWithAnswers(['answers' => []]),
    );
})->throws(ValidationException::class);

it('drops and logs an answer for a question not in the effective set (edge case)', function () {
    Log::shouldReceive('info')->once()->withArgs(
        fn (string $message, array $context) => $context['question_key'] === 'not_a_real_question',
    );

    $category = categoryWithQuestionSet();
    $business = Business::factory()->create(['primary_category_id' => $category->id]);
    $reviewer = User::factory()->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewDataWithAnswers(['answers' => [
            'cleanliness' => 4,
            'not_a_real_question' => 'ignored',
        ]]),
    );

    expect($review->answers)->toBe(['cleanliness' => 4]);
});

it('stores no question set version or answers when the business has no primary category', function () {
    $business = Business::factory()->create();
    $reviewer = User::factory()->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewDataWithAnswers(['answers' => ['cleanliness' => 4]]),
    );

    expect($review->question_set_version)->toBeNull()
        ->and($review->answers)->toBeNull();
});

it('stores the location on a location review (FR-003-01)', function () {
    $business = Business::factory()->create();
    $location = Location::factory()->for($business)->create();
    $reviewer = User::factory()->create();

    $review = (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewDataWithAnswers(),
        $location,
    );

    expect($review->location_id)->toBe($location->id);
});

it('rejects a location that does not belong to the reviewed business', function () {
    $business = Business::factory()->create();
    $otherBusinessLocation = Location::factory()->create();
    $reviewer = User::factory()->create();

    (new SubmitReview(new ScreenReviewSubmission))->handle(
        $reviewer,
        $business,
        validReviewDataWithAnswers(),
        $otherBusinessLocation,
    );
})->throws(ValidationException::class);

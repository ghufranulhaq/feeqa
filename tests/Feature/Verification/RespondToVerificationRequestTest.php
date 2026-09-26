<?php

use App\Actions\Verification\IssueAttestation;
use App\Actions\Verification\MatchReviewReference;
use App\Actions\Verification\RespondToVerificationRequest;
use App\Domain\Verification\ConsumerVerificationResponse;
use App\Domain\Verification\TransactionRecordHash;
use App\Domain\Verification\VerificationRequestStatus;
use App\Drivers\Signing\SigningService;
use App\Models\Business;
use App\Models\BusinessTransactionRecord;
use App\Models\BusinessVerificationRequest;
use App\Models\Review;
use App\Models\User;
use App\Notifications\VerificationRequestRespondedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function respondToVerificationRequest(): RespondToVerificationRequest
{
    $signing = new SigningService(keysPath: storage_path('framework/testing/signing-'.uniqid().'.json'));
    $signing->generateKey();

    return new RespondToVerificationRequest(new MatchReviewReference(new IssueAttestation($signing)));
}

function verifiedReviewerForRequest(): User
{
    return User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => now()]);
}

it('ignores a request without changing the review (FR-004-20)', function () {
    Notification::fake();
    $reviewer = verifiedReviewerForRequest();
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);
    $verificationRequest = BusinessVerificationRequest::factory()->create(['review_id' => $review->id]);

    respondToVerificationRequest()->handle($reviewer, $verificationRequest, ConsumerVerificationResponse::Ignore);

    expect($verificationRequest->fresh()->status)->toBe(VerificationRequestStatus::Ignored)
        ->and($review->fresh()->isVerified())->toBeFalse();
    Notification::assertSentTo($verificationRequest->requestedBy, VerificationRequestRespondedNotification::class);
});

it('verifies privately and does not share the reference number', function () {
    $reviewer = verifiedReviewerForRequest();
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $reviewer->id]);
    $verificationRequest = BusinessVerificationRequest::factory()->for($business)->create(['review_id' => $review->id]);
    BusinessTransactionRecord::factory()->for($business)->create([
        'reference_hash' => TransactionRecordHash::reference('ABC-1'),
        'email_hash' => TransactionRecordHash::email('jane@example.com'),
        'transaction_date' => now()->subDays(5)->toDateString(),
    ]);

    respondToVerificationRequest()->handle($reviewer, $verificationRequest, ConsumerVerificationResponse::VerifyPrivately, 'ABC-1');

    expect($verificationRequest->fresh()->status)->toBe(VerificationRequestStatus::Responded)
        ->and($verificationRequest->fresh()->shared_reference_number)->toBeNull()
        ->and($review->fresh()->isVerified())->toBeTrue();
});

it('verifies and shares the reference number with the business', function () {
    $reviewer = verifiedReviewerForRequest();
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $reviewer->id]);
    $verificationRequest = BusinessVerificationRequest::factory()->for($business)->create(['review_id' => $review->id]);
    BusinessTransactionRecord::factory()->for($business)->create([
        'reference_hash' => TransactionRecordHash::reference('ABC-1'),
        'email_hash' => TransactionRecordHash::email('jane@example.com'),
        'transaction_date' => now()->subDays(5)->toDateString(),
    ]);

    respondToVerificationRequest()->handle($reviewer, $verificationRequest, ConsumerVerificationResponse::VerifyAndShare, 'ABC-1');

    expect($verificationRequest->fresh()->shared_reference_number)->toBe('ABC-1')
        ->and($review->fresh()->isVerified())->toBeTrue();
});

it('rejects a non-author responding to a request', function () {
    $review = Review::factory()->create();
    $verificationRequest = BusinessVerificationRequest::factory()->create(['review_id' => $review->id]);
    $notAuthor = verifiedReviewerForRequest();

    respondToVerificationRequest()->handle($notAuthor, $verificationRequest, ConsumerVerificationResponse::Ignore);
})->throws(AuthorizationException::class);

it('rejects responding to a request that already has a response', function () {
    $reviewer = verifiedReviewerForRequest();
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);
    $verificationRequest = BusinessVerificationRequest::factory()->create([
        'review_id' => $review->id,
        'status' => VerificationRequestStatus::Ignored,
    ]);

    respondToVerificationRequest()->handle($reviewer, $verificationRequest, ConsumerVerificationResponse::Ignore);
})->throws(ValidationException::class);

it('requires a reference number to verify', function () {
    $reviewer = verifiedReviewerForRequest();
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);
    $verificationRequest = BusinessVerificationRequest::factory()->create(['review_id' => $review->id]);

    respondToVerificationRequest()->handle($reviewer, $verificationRequest, ConsumerVerificationResponse::VerifyPrivately);
})->throws(ValidationException::class);

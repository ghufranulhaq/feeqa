<?php

use App\Actions\Verification\IssueAttestation;
use App\Actions\Verification\MatchReviewReference;
use App\Domain\Verification\TransactionRecordHash;
use App\Domain\Verification\VerificationStatus;
use App\Drivers\Signing\SigningService;
use App\Models\Business;
use App\Models\BusinessTransactionRecord;
use App\Models\Review;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

function matchReviewReference(): MatchReviewReference
{
    $signing = new SigningService(keysPath: storage_path('framework/testing/signing-'.uniqid().'.json'));
    $signing->generateKey();

    return new MatchReviewReference(new IssueAttestation($signing));
}

function verifiedReviewer(): User
{
    return User::factory()->create(['email' => 'jane@example.com', 'email_verified_at' => now()]);
}

it('auto-verifies when both the reference and email hash match (FR-004-13)', function () {
    $reviewer = verifiedReviewer();
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $reviewer->id]);
    BusinessTransactionRecord::factory()->for($business)->create([
        'reference_hash' => TransactionRecordHash::reference('ABC-1'),
        'email_hash' => TransactionRecordHash::email('jane@example.com'),
        'transaction_date' => now()->subDays(10)->toDateString(),
    ]);

    $verification = matchReviewReference()->handle($reviewer, $review, 'ABC-1');

    expect($verification->status)->toBe(VerificationStatus::Approved)
        ->and($review->fresh()->isVerified())->toBeTrue();
});

it("holds for the staff queue when the reference matches but the email doesn't", function () {
    $reviewer = verifiedReviewer();
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $reviewer->id]);
    BusinessTransactionRecord::factory()->for($business)->create([
        'reference_hash' => TransactionRecordHash::reference('ABC-1'),
        'email_hash' => TransactionRecordHash::email('someone-else@example.com'),
        'transaction_date' => now()->subDays(10)->toDateString(),
    ]);

    $verification = matchReviewReference()->handle($reviewer, $review, 'ABC-1');

    expect($verification->status)->toBe(VerificationStatus::Pending)
        ->and($verification->extracted_fields['auto_approval_hold_reason'])->toBe('reference_matched_email_did_not')
        ->and($review->fresh()->isVerified())->toBeFalse();
});

it('rejects outright when no transaction record matches the reference at all', function () {
    $reviewer = verifiedReviewer();
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);

    $verification = matchReviewReference()->handle($reviewer, $review, 'NO-SUCH-REF');

    expect($verification->status)->toBe(VerificationStatus::Rejected)
        ->and($verification->decision_reason_code)->toBe('reference_not_found');
});

it('does not match a transaction record older than 12 months', function () {
    $reviewer = verifiedReviewer();
    $business = Business::factory()->create();
    $review = Review::factory()->for($business)->create(['reviewer_id' => $reviewer->id]);
    BusinessTransactionRecord::factory()->for($business)->create([
        'reference_hash' => TransactionRecordHash::reference('ABC-1'),
        'email_hash' => TransactionRecordHash::email('jane@example.com'),
        'transaction_date' => now()->subMonths(13)->toDateString(),
    ]);

    $verification = matchReviewReference()->handle($reviewer, $review, 'ABC-1');

    expect($verification->status)->toBe(VerificationStatus::Rejected)
        ->and($verification->decision_reason_code)->toBe('reference_not_found');
});

it('rejects a non-author requesting reference matching', function () {
    $review = Review::factory()->create();
    $notAuthor = verifiedReviewer();

    matchReviewReference()->handle($notAuthor, $review, 'ABC-1');
})->throws(AuthorizationException::class);

it('rejects reference matching for an unpublished review', function () {
    $reviewer = verifiedReviewer();
    $review = Review::factory()->held()->create(['reviewer_id' => $reviewer->id]);

    matchReviewReference()->handle($reviewer, $review, 'ABC-1');
})->throws(ValidationException::class);

it('requires a verified email before matching', function () {
    $reviewer = User::factory()->create(['email_verified_at' => null]);
    $review = Review::factory()->create(['reviewer_id' => $reviewer->id]);

    matchReviewReference()->handle($reviewer, $review, 'ABC-1');
})->throws(ValidationException::class);

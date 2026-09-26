<?php

namespace App\Actions\Verification;

use App\Domain\Verification\ConsumerVerificationResponse;
use App\Domain\Verification\VerificationRequestStatus;
use App\Models\BusinessVerificationRequest;
use App\Models\User;
use App\Notifications\VerificationRequestRespondedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * FR-004-19: the reviewer's response to a business's verification
 * request. "Verify privately" and "verify and share" both run the same
 * reference-matching flow (T6) a reviewer would use on their own
 * initiative — the only difference is whether the reference number is
 * also shared with the business. FR-004-20: ignoring never changes the
 * review itself.
 */
class RespondToVerificationRequest
{
    public function __construct(private readonly MatchReviewReference $matchReviewReference) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(
        User $actor,
        BusinessVerificationRequest $request,
        ConsumerVerificationResponse $response,
        ?string $reference = null,
    ): BusinessVerificationRequest {
        if ($request->review->reviewer_id !== $actor->id) {
            throw new AuthorizationException('Only the review author can respond to this request.');
        }

        if ($request->status !== VerificationRequestStatus::Pending) {
            throw ValidationException::withMessages(['request' => 'This request has already been responded to.']);
        }

        if ($response === ConsumerVerificationResponse::Ignore) {
            $request->forceFill([
                'status' => VerificationRequestStatus::Ignored,
                'consumer_response' => $response,
                'responded_at' => now(),
            ])->save();

            $request->requestedBy->notify(new VerificationRequestRespondedNotification($request));

            return $request;
        }

        if ($reference === null || $reference === '') {
            throw ValidationException::withMessages(['reference' => 'Please enter your reference number to verify.']);
        }

        $verification = $this->matchReviewReference->handle($actor, $request->review, $reference);
        $verification->forceFill(['business_verification_request_id' => $request->id])->save();

        $request->forceFill([
            'status' => VerificationRequestStatus::Responded,
            'consumer_response' => $response,
            'responded_at' => now(),
            'shared_reference_number' => $response === ConsumerVerificationResponse::VerifyAndShare ? $reference : null,
        ])->save();

        $request->requestedBy->notify(new VerificationRequestRespondedNotification($request));

        return $request;
    }
}

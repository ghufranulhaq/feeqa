<?php

namespace App\Actions\Verification;

use App\Domain\Verification\VerificationStatus;
use App\Drivers\Signing\SigningService;
use App\Models\ReviewVerification;
use App\Models\User;
use App\Models\VerificationAttestation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

/**
 * FR-004-14: the single choke point every approval path (automatic
 * auto-approval, and T5's manual staff decision once it exists) goes
 * through to produce a signed attestation and flip on the review's
 * computed verified state (Review::isVerified()). Guarding "a staff
 * member can't approve their own review's proof" here, rather than only
 * in the staff queue action, makes the rule hold for every caller.
 */
class IssueAttestation
{
    public function __construct(private readonly SigningService $signing) {}

    /**
     * @throws AuthorizationException
     */
    public function handle(ReviewVerification $verification, ?User $decidedBy = null): VerificationAttestation
    {
        if ($decidedBy !== null && $verification->review->reviewer_id === $decidedBy->id) {
            throw new AuthorizationException("A staff member can't decide their own review's proof.");
        }

        $review = $verification->review;
        $decisionTime = now();
        $methodologyVersion = (int) config('platform.verification.methodology_version', 1);
        $id = (string) Str::orderedUuid();

        $verification->forceFill([
            'status' => VerificationStatus::Approved,
            'decided_by' => $decidedBy?->id,
            'decided_at' => $decisionTime,
            'decision_reason_code' => 'approved',
        ])->save();

        // FR-004-14: "experience month (not the exact date)" — the payload
        // deliberately carries less precision than the review itself.
        $experienceMonth = $review->date_of_experience->copy()->startOfMonth();

        $jws = $this->signing->sign([
            'attestation_id' => $id,
            'review_id' => $review->id,
            'business_id' => $review->business_id,
            'method' => $verification->method->value,
            'proof_fingerprint' => $verification->proof_fingerprint,
            'experience_month' => $experienceMonth->toDateString(),
            'decision_time' => $decisionTime->toIso8601String(),
            'methodology_version' => $methodologyVersion,
        ]);

        return VerificationAttestation::create([
            'id' => $id,
            'review_id' => $review->id,
            'business_id' => $review->business_id,
            'verification_id' => $verification->id,
            'method' => $verification->method,
            'experience_month' => $experienceMonth,
            'decision_time' => $decisionTime,
            'methodology_version' => $methodologyVersion,
            'jws' => $jws,
        ]);
    }
}

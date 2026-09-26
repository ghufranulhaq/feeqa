<?php

namespace App\Domain\Invitations;

/**
 * FR-005-18. An immutable snapshot ComputeNeutralityReport builds — a
 * `null` metric means "not enough data to say," not zero: `cancellationRate`
 * needs the ≥ 50-invitation gate, the rating averages need at least one
 * published review of that source label, and `transactionsInvitedPercentage`
 * needs the Business to have submitted at least one transaction record
 * ("when transaction data is available", FR-005-18).
 */
final class NeutralityReport
{
    /**
     * @param  array<string, int>  $invitationsPerMethod
     */
    public function __construct(
        public readonly int $businessId,
        public readonly array $invitationsPerMethod,
        public readonly int $totalInvitations,
        public readonly int $cancelledInvitations,
        public readonly ?float $cancellationRate,
        public readonly ?float $transactionsInvitedPercentage,
        public readonly ?float $invitedAverageRating,
        public readonly int $invitedReviewCount,
        public readonly ?float $organicAverageRating,
        public readonly int $organicReviewCount,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'business_id' => $this->businessId,
            'invitations_per_method' => $this->invitationsPerMethod,
            'total_invitations' => $this->totalInvitations,
            'cancelled_invitations' => $this->cancelledInvitations,
            'cancellation_rate' => $this->cancellationRate,
            'transactions_invited_percentage' => $this->transactionsInvitedPercentage,
            'invited_average_rating' => $this->invitedAverageRating,
            'invited_review_count' => $this->invitedReviewCount,
            'organic_average_rating' => $this->organicAverageRating,
            'organic_review_count' => $this->organicReviewCount,
        ];
    }
}

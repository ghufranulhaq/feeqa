<?php

namespace Database\Factories;

use App\Domain\Verification\VerificationMethod;
use App\Domain\Verification\VerificationStatus;
use App\Models\Review;
use App\Models\ReviewVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewVerification>
 */
class ReviewVerificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'method' => VerificationMethod::DocumentProof,
            'status' => VerificationStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => VerificationStatus::Approved,
            'decided_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => VerificationStatus::Rejected,
            'decided_at' => now(),
            'decision_reason_code' => 'merchant_mismatch',
        ]);
    }
}

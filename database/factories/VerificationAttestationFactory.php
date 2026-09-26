<?php

namespace Database\Factories;

use App\Domain\Verification\VerificationMethod;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewVerification;
use App\Models\VerificationAttestation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationAttestation>
 */
class VerificationAttestationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'business_id' => Business::factory(),
            'verification_id' => ReviewVerification::factory(),
            'method' => VerificationMethod::DocumentProof,
            'experience_month' => now()->subMonth()->startOfMonth(),
            'decision_time' => now(),
            'methodology_version' => 1,
            // Not a real signature — tests that check signing use the
            // real SigningService directly (T4), this factory only needs
            // a plausible three-part JWS shape for row creation.
            'jws' => $this->faker->regexify('[A-Za-z0-9_-]{20}').'.'.$this->faker->regexify('[A-Za-z0-9_-]{40}').'.'.$this->faker->regexify('[A-Za-z0-9_-]{86}'),
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
            'revoked_reason_code' => 'fraud_found',
        ]);
    }
}

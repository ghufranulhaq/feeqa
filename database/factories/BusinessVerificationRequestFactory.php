<?php

namespace Database\Factories;

use App\Domain\Verification\VerificationRequestStatus;
use App\Models\Business;
use App\Models\BusinessVerificationRequest;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessVerificationRequest>
 */
class BusinessVerificationRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'review_id' => Review::factory(),
            'requested_by' => User::factory(),
            'requested_at' => now(),
            'status' => VerificationRequestStatus::Pending,
        ];
    }
}

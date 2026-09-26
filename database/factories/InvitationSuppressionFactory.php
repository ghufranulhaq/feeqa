<?php

namespace Database\Factories;

use App\Domain\Verification\TransactionRecordHash;
use App\Models\Business;
use App\Models\InvitationSuppression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvitationSuppression>
 */
class InvitationSuppressionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'recipient_email_hash' => TransactionRecordHash::email($this->faker->unique()->safeEmail()),
            'reason' => 'unsubscribed_business',
        ];
    }

    public function global(): static
    {
        return $this->state(fn () => ['business_id' => null, 'reason' => 'unsubscribed_global']);
    }
}

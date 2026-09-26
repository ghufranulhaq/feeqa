<?php

namespace Database\Factories;

use App\Domain\Moderation\FlagStatus;
use App\Domain\Moderation\ReasonCode;
use App\Models\Flag;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flag>
 */
class FlagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flaggable_type' => (new Review)->getMorphClass(),
            'flaggable_id' => Review::factory(),
            'reporter_id' => User::factory(),
            'reason_code' => ReasonCode::AdvertisingSpam,
            'status' => FlagStatus::Open,
            'is_business_flag' => false,
            'sla_due_at' => now()->addDays(7),
        ];
    }
}

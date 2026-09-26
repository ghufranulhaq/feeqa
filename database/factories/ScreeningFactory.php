<?php

namespace Database\Factories;

use App\Domain\Reviews\ReviewStatus;
use App\Models\Review;
use App\Models\Screening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Screening>
 */
class ScreeningFactory extends Factory
{
    protected $model = Screening::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'screenable_type' => (new Review)->getMorphClass(),
            'screenable_id' => Review::factory(),
            'recommendation' => ReviewStatus::Published,
            'risk_score' => 0.0,
            'triggered_rules' => [],
            'signals' => [],
        ];
    }
}

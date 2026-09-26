<?php

namespace Database\Factories;

use App\Models\TransparencyReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransparencyReport>
 */
class TransparencyReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 year', 'now');

        return [
            'period_start' => $start,
            'period_end' => (clone $start)->modify('+3 months'),
            'figures' => [],
            'generated_at' => now(),
        ];
    }
}

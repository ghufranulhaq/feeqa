<?php

namespace Database\Factories;

use App\Domain\Businesses\BusinessStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Business>
 */
class BusinessFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company();
        $domain = str($name)->slug().'.example';

        return [
            'name' => $name,
            'primary_domain' => $domain,
            'country' => $this->faker->randomElement(['GB', 'IE', 'FR', 'DE', 'ES']),
            'status' => BusinessStatus::Unclaimed,
        ];
    }

    public function claimed(): static
    {
        return $this->state(fn () => [
            'status' => BusinessStatus::Claimed,
            'claimed_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => BusinessStatus::Pending]);
    }
}

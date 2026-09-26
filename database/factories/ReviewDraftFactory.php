<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\ReviewDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewDraft>
 */
class ReviewDraftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_id' => Business::factory(),
            'payload' => [
                'star_rating' => $this->faker->numberBetween(1, 5),
                'title' => $this->faker->sentence(4),
                'text' => $this->faker->paragraph(),
            ],
        ];
    }
}

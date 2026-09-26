<?php

namespace Database\Factories;

use App\Domain\Reviews\ReviewStatus;
use App\Domain\Reviews\SourceLabel;
use App\Models\Business;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $publishedAt = $this->faker->dateTimeBetween('-11 months', '-1 week');

        return [
            'business_id' => Business::factory(),
            'reviewer_id' => User::factory(),
            'status' => ReviewStatus::Published,
            'source_label' => SourceLabel::Organic,
            'star_rating' => $this->faker->numberBetween(1, 5),
            'title' => $this->faker->sentence(4),
            'text' => $this->faker->paragraphs(2, true),
            'date_of_experience' => $this->faker->dateTimeBetween('-11 months', $publishedAt),
            'confirmed_genuine' => true,
            'published_at' => $publishedAt,
        ];
    }

    public function held(): static
    {
        return $this->state(fn () => ['status' => ReviewStatus::Held, 'published_at' => null]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => ['status' => ReviewStatus::Rejected, 'published_at' => null]);
    }
}

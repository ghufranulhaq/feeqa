<?php

namespace Database\Factories;

use App\Domain\Moderation\GuidelineAudience;
use App\Models\GuidelineVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuidelineVersion>
 */
class GuidelineVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'audience' => GuidelineAudience::Reviewer,
            'version' => 1,
            'body' => $this->faker->paragraphs(3, true),
            'published_at' => now(),
            'is_current' => true,
        ];
    }
}

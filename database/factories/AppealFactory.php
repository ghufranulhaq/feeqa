<?php

namespace Database\Factories;

use App\Domain\Moderation\AppealStatus;
use App\Models\Appeal;
use App\Models\Flag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appeal>
 */
class AppealFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appealable_type' => (new Flag)->getMorphClass(),
            'appealable_id' => Flag::factory(),
            'appellant_id' => User::factory(),
            'statement' => $this->faker->paragraph(),
            'status' => AppealStatus::Pending,
        ];
    }
}

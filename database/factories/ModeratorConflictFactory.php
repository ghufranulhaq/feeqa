<?php

namespace Database\Factories;

use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\ModeratorConflict;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModeratorConflict>
 */
class ModeratorConflictFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => User::factory()->state(['staff_role' => StaffRole::Moderator->value]),
            'business_id' => Business::factory(),
        ];
    }
}

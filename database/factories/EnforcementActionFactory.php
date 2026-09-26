<?php

namespace Database\Factories;

use App\Domain\Moderation\EnforcementLadder;
use App\Domain\Moderation\EnforcementStep;
use App\Domain\Moderation\ReasonCode;
use App\Domain\Staff\StaffRole;
use App\Models\Business;
use App\Models\EnforcementAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnforcementAction>
 */
class EnforcementActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_id' => Business::factory(),
            'subject_type' => Business::class,
            'ladder' => EnforcementLadder::Business,
            'step' => EnforcementStep::EducationalNotice,
            'reason_code' => ReasonCode::AdvertisingSpam,
            'applied_by' => User::factory()->state(['staff_role' => StaffRole::Moderator->value]),
            'applied_at' => now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Domain\Moderation\IncidentStatus;
use App\Domain\Moderation\IncidentType;
use App\Models\Business;
use App\Models\ModerationIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModerationIncident>
 */
class ModerationIncidentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'type' => IncidentType::ReviewSpike,
            'status' => IncidentStatus::Open,
            'detected_at' => now(),
            'metrics' => [],
        ];
    }
}

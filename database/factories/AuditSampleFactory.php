<?php

namespace Database\Factories;

use App\Models\AuditSample;
use App\Models\Screening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditSample>
 */
class AuditSampleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'screening_id' => Screening::factory(),
        ];
    }
}

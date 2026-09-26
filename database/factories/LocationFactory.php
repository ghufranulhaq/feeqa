<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->city().' Branch';

        return [
            'business_id' => Business::factory(),
            'name' => $name,
            'slug' => str($name)->slug(),
            'address' => [
                'line1' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'postal_code' => $this->faker->postcode(),
                'country' => 'GB',
            ],
        ];
    }
}

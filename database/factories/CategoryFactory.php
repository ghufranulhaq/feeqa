<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'parent_id' => null,
            'slug' => str($name)->slug(),
            'name' => ['en-GB' => ucfirst($name)],
            'launched' => false,
            'state' => null,
            'is_system' => false,
        ];
    }

    public function launched(): static
    {
        return $this->state(fn () => ['launched' => true]);
    }

    public function industry(): static
    {
        return $this->state(fn () => ['state' => 'draft']);
    }
}

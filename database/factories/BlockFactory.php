<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Generate year-level / section blocks linked to a course.
 *
 * @extends Factory<Block>
 */
class BlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'year_level' => fake()->numberBetween(1, 4),
            'section' => fake()->randomLetter(),
        ];
    }
}

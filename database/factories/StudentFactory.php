<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Generate student records linked to a user, course, and block
 * with a unique student ID and active status by default.
 *
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'student_id' => fake()->unique()->numerify('##########'),
            'course_id' => Course::factory(),
            'block_id' => Block::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'status' => 'active',
            'has_voted' => false,
        ];
    }
}

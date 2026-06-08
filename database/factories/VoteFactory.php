<?php

namespace Database\Factories;

use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vote>
 */
class VoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => \App\Models\Student::factory(),
            'candidate_id' => \App\Models\Candidate::factory(),
            'position_id' => \App\Models\Position::factory(),
            'election_cycle_id' => \App\Models\ElectionCycle::factory(),
            'reference_number' => fake()->uuid(),
            'voted_at' => now(),
        ];
    }
}

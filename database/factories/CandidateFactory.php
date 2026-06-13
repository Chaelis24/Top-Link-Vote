<?php

namespace Database\Factories;

use App\Models\Candidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Generate candidate records with associated user, student, position,
 * and election cycle for testing the election workflow.
 *
 * @extends Factory<Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'student_id' => \App\Models\Student::factory(),
            'position_id' => \App\Models\Position::factory(),
            'election_cycle_id' => \App\Models\ElectionCycle::factory(),
            'party_name' => fake()->company(),
            'status' => 'approved',
            'votes_count' => 0,
        ];
    }
}

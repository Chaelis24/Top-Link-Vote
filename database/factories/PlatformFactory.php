<?php

namespace Database\Factories;

use App\Models\Platform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Platform>
 */
class PlatformFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'candidate_id' => \App\Models\Candidate::factory(),
            'title' => fake()->sentence(3),
            'tagline' => fake()->catchPhrase(),
            'agenda' => [fake()->paragraph(3)],
            'status' => 'pending',
            'submitted_at' => now(),
        ];
    }
}

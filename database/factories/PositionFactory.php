<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'election_cycle_id' => \App\Models\ElectionCycle::factory(),
            'name' => 'President',
            'max_candidates' => 10,
            'max_winners' => 1,
            'priority' => 1,
            'is_active' => true,
        ];
    }
}

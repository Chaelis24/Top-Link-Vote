<?php

namespace Database\Factories;

use App\Models\ElectionCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Generate election cycles with default dates relative to `now`
 * so that the cycle is in a predictable state for testing.
 *
 * @extends Factory<ElectionCycle>
 */
class ElectionCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'General Election 2026',
            'status' => 'active',
            'academic_year' => '2025-2026',
            'campaign_start' => now(),
            'campaign_end'   => now()->addDays(3),
            'voting_start'   => now()->addDays(4),
            'voting_end'     => now()->addDays(7),
        ];
    }
}

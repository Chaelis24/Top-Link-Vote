<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\ElectionCycle;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = ElectionCycle::first();

        $positions = [
            ['name' => 'President', 'max_candidates' => 5, 'max_winners' => 1, 'priority' => 1],
            ['name' => 'Vice President', 'max_candidates' => 5, 'max_winners' => 1, 'priority' => 2],
            ['name' => 'Senator', 'max_candidates' => 20, 'max_winners' => 12, 'priority' => 3],
        ];

        foreach ($positions as $pos) {
            Position::create(array_merge($pos, [
                'election_cycle_id' => $cycle->id,
                'is_active' => true
            ]));
        }
    }
}

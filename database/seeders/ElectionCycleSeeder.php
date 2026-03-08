<?php

namespace Database\Seeders;

use App\Models\ElectionCycle;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ElectionCycleSeeder extends Seeder
{
    public function run(): void
    {
        ElectionCycle::create([
            'name' => 'SSC General Elections 2026',
            'academic_year' => '2025-2026',
            'semester' => '2nd Semester',
            'filing_start' => Carbon::now()->subDays(10),
            'filing_end' => Carbon::now()->subDays(5),
            'campaign_start' => Carbon::now()->subDays(4),
            'campaign_end' => Carbon::now()->subDays(1),
            'voting_start' => Carbon::now(),
            'voting_end' => Carbon::now()->addDays(2),
            'results_date' => Carbon::now()->addDays(3),
            'status' => 'draft',
            'is_active' => true,
        ]);
    }
}

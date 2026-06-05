<?php

namespace Database\Seeders;

use App\Models\ElectionCycle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ElectionCycleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ElectionCycle::create([
            'name'           => 'SSG Election 2026',
            'academic_year'  => '2026-2027',
            'status'         => 'active',

            'filing_start'   => Carbon::now()->startOfDay(),
            'filing_end'     => Carbon::now()->addDays(1)->endOfDay(),
            'campaign_start' => Carbon::now()->addDays(2)->startOfDay(),
            'campaign_end'   => Carbon::now()->addDays(3)->endOfDay(),

            'voting_start'   => Carbon::now()->addDays(4)->startOfDay(),
            'voting_end'     => Carbon::now()->addDays(5)->endOfDay(),
            'results_date'   => Carbon::now()->addDays(6)->startOfDay(),
        ]);
    }
}

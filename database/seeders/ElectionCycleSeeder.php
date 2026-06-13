<?php

namespace Database\Seeders;

use App\Models\ElectionCycle;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a new active election cycle and resets all students'
 * voting flags so they can vote fresh. The cycle dates are set
 * relative to `now` to keep them valid during development.
 */
class ElectionCycleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            Student::query()->update([
                'vote_reference' => null,
                'has_voted'       => 0,
                'voted_at'       => null
            ]);

            ElectionCycle::create([
                'name'           => 'CSC Official Election 2026',
                'academic_year'  => '2026-2027',
                'status'         => 'active',

                'filing_start'   => Carbon::now()->startOfDay(),
                'filing_end'     => Carbon::now()->addDays(2)->endOfDay(),
                'campaign_start' => Carbon::now()->addDays(4)->startOfDay(),
                'campaign_end'   => Carbon::now()->addDays(6)->endOfDay(),

                'voting_start'   => Carbon::now()->addDays(8)->startOfDay(),
                'voting_end'     => Carbon::now()->addDays(10)->endOfDay(),
                'results_date'   => Carbon::now()->addDays(12)->startOfDay(),
            ]);
        });
    }
}

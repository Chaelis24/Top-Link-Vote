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

            $now = Carbon::now();

            $duration = 3;

            $f_start = $now->copy()->startOfDay();
            $f_end   = $f_start->copy()->addDays($duration)->endOfDay();

            $c_start = $f_end->copy()->addSecond();
            $c_end   = $c_start->copy()->addDays($duration)->endOfDay();

            $v_start = $c_end->copy()->addSecond();
            $v_end   = $v_start->copy()->addDays($duration)->endOfDay();

            $res_date = $v_end->copy()->addDay()->startOfDay();

            ElectionCycle::create([
                'name'           => 'CSC Official Election 2026',
                'academic_year'  => '2026-2027',
                'status'         => 'active',

                'filing_start'   => $f_start,
                'filing_end'     => $f_end,
                'campaign_start' => $c_start,
                'campaign_end'   => $c_end,
                'voting_start'   => $v_start,
                'voting_end'     => $v_end,
                'results_date'   => $res_date,
            ]);
        });
    }
}

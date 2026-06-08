<?php

namespace Database\Seeders;

use App\Models\ElectionCycle;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ElectionCycleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {

            ElectionCycle::where('status', 'active')
                ->update(['status' => 'completed',
                    'filing_end'    => Carbon::now(),
                    'campaign_end'  => Carbon::now(),
                ]);

            Student::query()->update([
                'vote_reference' => null,
                'has_voted'       => 0,
                'voted_at'       => null
            ]);

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
        });
    }
}

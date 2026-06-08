<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MasterElectionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            $this->command->info('Starting election reset process...');

            $this->call(ElectionCycleSeeder::class);
            $this->command->info('Election Cycle updated.');

            $this->call(CandidateSeeder::class);
            $this->command->info('Candidates seeded.');

            // $this->call(VotingKeySeeder::class);

            $this->command->info('All election processes completed successfully!');
        });
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates a full election reset by calling ElectionCycleSeeder
 * (resets student flags + creates a new cycle) followed by
 * CandidateSeeder (re-populates candidates).
 */
class MasterElectionSeeder extends Seeder
{
    /**
     * Run the election reset pipeline inside a single transaction.
     */
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

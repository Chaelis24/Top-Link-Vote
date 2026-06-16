<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Student;
use App\Models\Position;
use App\Models\ElectionCycle;
use Illuminate\Database\Seeder;

/**
 * Creates candidate records for every student who has been
 * assigned the `candidate` role. Each candidate is linked to
 * the currently active election cycle and the first available position.
 */
class CandidateSeeder extends Seeder
{
    /**
     * Find students with the `candidate` role and create or update
     * their candidacy under the active election cycle.
     */
    public function run(): void
    {
        $election = ElectionCycle::where('status', 'active')->first();

        if (!$election) {
            $this->command->error("No active election cycle found. Cannot seed candidates.");
            return;
        }

        $students = Student::whereHas('user', function ($query) {
            $query->whereHas('roles', function ($q) {
                $q->where('name', 'candidate');
            });
        })->get();

        if ($students->isEmpty()) {
            $this->command->warn("No students found with the 'candidate' role.");
            return;
        }

        foreach ($students as $student) {
            $position = Position::first();

            Candidate::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'election_cycle_id' => $election->id,
                ],
                [
                    'user_id'     => $student->user_id,
                    'position_id' => $position->id ?? null,
                    'status'      => 'approved',
                ]
            );
        }

        $this->command->info("Candidates seeded successfully!");
    }
}

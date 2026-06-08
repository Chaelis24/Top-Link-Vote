<?php

namespace Database\Seeders;

use App\Models\Candidate;
use App\Models\Student;
use App\Models\Position;
use App\Models\ElectionCycle;
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        $election = ElectionCycle::where('status', 'active')->first();

        if (!$election) {
            $this->command->error("No active election cycle found. Cannot seed candidates.");
            return;
        }

        $students = Student::whereHas('user', function ($query) {
            $query->role('candidate');
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
                    'status'      => 'pending',
                ]
            );
        }

        $this->command->info("Candidates seeded successfully!");
    }
}

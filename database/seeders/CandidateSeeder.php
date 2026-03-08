<?php

namespace Database\Seeders;

use App\Models\{Student, Position, Candidate, ElectionCycle, Platform, Role, User};
use Illuminate\Database\Seeder;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = ElectionCycle::firstOrCreate(
            ['is_active' => true],
            ['name' => 'SC Election 2026', 'starts_at' => now(), 'ends_at' => now()->addDays(7)]
        );

        $candidateRole = Role::where('name', 'Candidate')->first();
        $presidentPos = Position::where('name', 'President')->first();
        $positions = Position::all();
        $courses = ['IT', 'HRMT', 'HST', 'ECT'];

        $ericaUser = User::where('email', 'mira.erica08@gmail.com')->first();
        if ($ericaUser && $ericaUser->student) {
            $student = $ericaUser->student;
            $this->createCandidate($student, $presidentPos, $cycle, $candidateRole, "Lakas HRMT");
        }

        foreach ($courses as $course) {
            $coursePool = Student::where('course', $course)
                ->whereHas('user', function ($query) {
                    $query->whereNotIn('email', ['mira.erica08@gmail.com']);
                })
                ->get();

            foreach ($positions as $pIndex => $position) {
                $countNeeded = ($course === 'HRMT' && $position->name === 'President') ? 1 : 2;

                $offset = ($course === 'HRMT' && $position->name === 'President') ? 0 : ($pIndex * 2);
                $candidatesForPosition = $coursePool->slice($offset, $countNeeded);

                foreach ($candidatesForPosition as $index => $student) {
                    $party = ($index % 2 == 0) ? "Unity $course" : "Lakas $course";
                    $this->createCandidate($student, $position, $cycle, $candidateRole, $party);
                }
            }
        }
    }

    private function createCandidate($student, $position, $cycle, $role, $party)
    {
        $candidate = Candidate::create([
            'user_id'           => $student->user_id,
            'student_id'        => $student->id,
            'position_id'       => $position->id,
            'election_cycle_id' => $cycle->id,
            'course'            => $student->course,
            'party_name'        => $party,
            'votes_count'       => 0,
            'slogan'            => "Service Excellence for " . $student->course,
            'bio'               => "I am {$student->first_name}, dedicated to progress.",
            'status'            => 'approved',
            'approved_at'       => now(),
        ]);

        if ($role) {
            $student->user->roles()->syncWithoutDetaching([$role->id]);
        }

        Platform::create([
            'candidate_id' => $candidate->id,
            'title'        => 'Vision for ' . $position->name,
            'vision'       => 'A transparent student government.',
            'mission'      => 'Empowering students through representation.',
            'status'       => 'approved',
            'submitted_at' => now(),
        ]);
    }
}

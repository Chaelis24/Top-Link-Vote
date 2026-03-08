<?php

namespace Database\Seeders;

use App\Models\Vote;
use App\Models\Student;
use App\Models\Candidate;
use App\Models\Position;
use App\Models\ElectionCycle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VoteSeeder extends Seeder
{
    public function run(): void
    {
        $cycle = ElectionCycle::where('is_active', true)->first();
        if (!$cycle) return;

        $voters = Student::whereDoesntHave('candidate')
            ->where('has_voted', false)
            ->get();

        $positions = Position::where('election_cycle_id', $cycle->id)->get();

        foreach ($voters as $student) {
            DB::transaction(function () use ($student, $positions, $cycle) {
                foreach ($positions as $position) {
                    $candidate = Candidate::where('position_id', $position->id)
                        ->where('election_cycle_id', $cycle->id)
                        ->inRandomOrder()
                        ->first();

                    if ($candidate) {
                        Vote::create([
                            'student_id' => $student->id,
                            'candidate_id' => $candidate->id,
                            'position_id' => $position->id,
                            'election_cycle_id' => $cycle->id,
                            'voted_at' => now(),
                        ]);

                        $candidate->increment('votes_count');
                    }
                }

                $student->update([
                    'has_voted' => true,
                    'voted_at' => now(),
                ]);
            });
        }
    }
}

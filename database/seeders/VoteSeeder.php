<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{Student, Candidate, Vote, ElectionCycle, Position};
use Illuminate\Support\Str;
use App\Jobs\LogActivity;

class VoteSeeder extends Seeder
{
    public function run(): void
    {
        $activeCycle = ElectionCycle::where('status', 'active')->first();

        if ($activeCycle) {
            $students = Student::all()->shuffle();
            $voters = $students->take(ceil($students->count() / 2));
            $positions = Position::all();

            foreach ($voters as $student) {
                $referenceNumber = Str::upper(Str::random(10));
                $selections = [];

                foreach ($positions as $position) {
                    $candidate = Candidate::where('election_cycle_id', $activeCycle->id)
                        ->where('position_id', $position->id)
                        ->inRandomOrder()
                        ->first();

                    if ($candidate) {
                        Vote::updateOrCreate(
                            [
                                'student_id'        => $student->id,
                                'position_id'       => $position->id,
                                'election_cycle_id' => $activeCycle->id,
                            ],
                            [
                                'candidate_id'      => $candidate->id,
                                'reference_number'  => $referenceNumber,
                                'voted_at'          => now(),
                            ]
                        );

                        $selections[$position->id] = $candidate->id;
                    }
                }

                $student->update([
                    'has_voted' => true,
                    'voted_at' => now(),
                    'vote_reference' => $referenceNumber,
                ]);

                LogActivity::dispatch([
                    'user_id' => $student->user_id,
                    'student_id' => $student->id,
                    'action' => 'Voted (Seeder)',
                    'description' => "Automated vote cast: $referenceNumber",
                    'properties' => json_encode([
                        'selections' => $selections,
                        'reference' => $referenceNumber,
                    ]),
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'DatabaseSeeder/CLI',
                ])->onQueue('logs');
            }
        }

        cache()->forget('students_stats');
    }
}

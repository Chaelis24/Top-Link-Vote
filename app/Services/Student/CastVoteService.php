<?php

namespace App\Services\Student;

use App\Mail\VoteConfirmed;
use App\Models\{Setting, ElectionCycle, Position, Candidate, Vote, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Cache, DB, Mail, Log, RateLimiter};
use Illuminate\Support\Facades\Auth;

class CastVoteService
{
    public function getStudentData(User $user): array
    {
        $user->load('student');
        $student = $user->student;

        return [
            'student' => $student,
            'profile_photo_path' => $student?->photo ?? '',
        ];
    }

    public function getActiveCycle(): ?ElectionCycle
    {
        return ElectionCycle::where('status', 'active')->first();
    }

    public function isVotingOpen(?ElectionCycle $activeCycle): bool
    {
        $setting = Setting::where('key', 'allowVoting')->first();

        if (!$setting || !(bool) $setting->value) {
            return false;
        }

        if (!$activeCycle || now()->gt($activeCycle->voting_end)) {
            return false;
        }

        return true;
    }

    public function hasStudentVoted($student): bool
    {
        return $student && $student->has_voted;
    }

    public function getElectionData(?ElectionCycle $activeCycle, bool $isVotingOpen, int $voterCourseId): Collection
    {
        if (!$activeCycle || !$isVotingOpen) {
            return collect();
        }

        return Position::where('election_cycle_id', $activeCycle->id)
            ->where(function ($query) use ($voterCourseId) {
                $query->whereNull('student_department')
                    ->orWhere('student_department', '')
                    ->orWhere('student_department', $voterCourseId);
            })
            ->whereHas('candidates', function ($query) use ($voterCourseId) {
                $query->whereIn('status', ['approved', 'active'])
                    ->whereHas('student', fn($q) => $q->where('course_id', $voterCourseId));
            })
            ->with([
                'candidates' => function ($query) use ($voterCourseId) {
                    $query->whereIn('status', ['approved', 'active'])->with('student');
                },
            ])
            ->orderBy('priority', 'asc')
            ->get();
    }

    public function getVoterCourseId(): ?int
    {
        return Auth::user()->student->course_id ?? null;
    }

    public function submitVote(User $user, array $selections, ElectionCycle $cycle): array
    {
        $throttleKey = 'submit-vote:' . $user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            return ['error' => 'Please wait. Your vote is being processed.'];
        }

        RateLimiter::hit($throttleKey, 10);

        $student = $user->student;

        try {
            $voteStudent = null;

            $referenceNumber = DB::transaction(function () use ($student, $cycle, $user, $selections, &$voteStudent) {
                $lockedStudent = $user->student()->lockForUpdate()->first();
                $voteStudent = $lockedStudent;

                if ($lockedStudent->has_voted) {
                    throw new \Exception('Student has already voted.');
                }

                $referenceNumber = 'REF-' . strtoupper(bin2hex(random_bytes(4)));

                foreach ($selections as $positionId => $candidateId) {
                    $candidate = Candidate::findOrFail($candidateId);
                    if ((int) $candidate->position_id !== (int) $positionId) {
                        throw new \Exception('Invalid candidate selection for this position.');
                    }

                    Vote::updateOrCreate(
                        [
                            'student_id' => $lockedStudent->id,
                            'position_id' => $positionId,
                            'election_cycle_id' => $cycle->id,
                        ],
                        [
                            'candidate_id' => $candidateId,
                            'reference_number' => $referenceNumber,
                            'voted_at' => now(),
                        ]
                    );

                    Candidate::where('id', $candidateId)->increment('votes_count');
                }

                $lockedStudent->update([
                    'has_voted' => true,
                    'voted_at' => now(),
                    'vote_reference' => $referenceNumber,
                ]);

                $clientIp = request()->header('X-Forwarded-For')
                    ? explode(',', request()->header('X-Forwarded-For'))[0]
                    : request()->ip();

                if ($clientIp === '::1') {
                    $clientIp = '127.0.0.1';
                }

                \App\Jobs\LogActivity::dispatch([
                    'user_id' => $user->id,
                    'student_id' => $lockedStudent->id,
                    'action' => 'Voted',
                    'description' => $referenceNumber,
                    'properties' => [
                        'selections' => $selections,
                        'reference' => $referenceNumber,
                    ],
                    'ip_address' => $clientIp,
                    'user_agent' => request()->userAgent(),
                ])->onQueue('logs');

                $this->forgetTallyCache($cycle, $lockedStudent->course_id);

                try {
                    Mail::to($user->email)->send(new VoteConfirmed($lockedStudent, $cycle, $referenceNumber));
                } catch (\Exception $e) {
                    Log::error('Mail Error: ' . $e->getMessage());
                }

                return $referenceNumber;
            });

            RateLimiter::clear($throttleKey);

            if ($voteStudent && $voteStudent->course) {
                event(new \App\Events\VoteUpdated($voteStudent->course));
            }

            return ['success' => true, 'reference' => $referenceNumber, 'student' => $student->fresh()];
        } catch (\Exception $e) {
            Log::error('Vote Error: ' . $e->getMessage());
            return ['error' => $e->getMessage() === 'Student has already voted.'
                ? 'You have already submitted your ballot.'
                : 'Something went wrong while casting your vote.'];
        }
    }

    private function forgetTallyCache(ElectionCycle $cycle, ?int $courseId): void
    {
        $cacheKey = "tally_data_cycle_{$cycle->id}_course_{$courseId}";
        Cache::forget($cacheKey);
    }

    public function getAvatarColor(int $id): string
    {
        $colors = ['#10b981', '#3b82f6', '#6366f1', '#f59e0b', '#ef4444'];
        return $colors[$id % count($colors)];
    }
}

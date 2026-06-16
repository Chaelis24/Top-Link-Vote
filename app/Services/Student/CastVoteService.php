<?php

namespace App\Services\Student;

use App\Mail\VoteConfirmed;
use App\Models\{Setting, ElectionCycle, Position, Candidate, Vote, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Cache, DB, Mail, Log, RateLimiter};
use Illuminate\Support\Facades\Auth;

/**
 * Service for handling the electronic voting flow.
 *
 * Manages ballot data retrieval, vote submission with
 * pessimistic locking, rate limiting, and cache invalidation.
 */
class CastVoteService
{
    /**
     * Load the authenticated student's base data.
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    public function getStudentData(User $user): array
    {
        $user->load('student');
        $student = $user->student;

        return [
            'student' => $student,
            'profile_photo_path' => $student?->photo ?? '',
        ];
    }

    /**
     * Retrieve the currently active election cycle.
     *
     * @return \App\Models\ElectionCycle|null
     */
    public function getActiveCycle(): ?ElectionCycle
    {
        return ElectionCycle::where('status', 'active')->first();
    }

    /**
     * Determine whether voting is currently open.
     *
     * Validates the "allowVoting" setting and the voting date window.
     *
     * @param  \App\Models\ElectionCycle|null  $activeCycle
     * @return bool
     */
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

    /**
     * Check whether the filing period is currently open.
     *
     * @param  \App\Models\ElectionCycle|null  $cycle
     * @return bool
     */
    public function isFilingOpen(?ElectionCycle $cycle): bool
    {
        if (!$cycle || $cycle->status !== 'active') {
            return false;
        }
        return now()->between($cycle->filing_start, $cycle->filing_end);
    }

    /**
     * Check whether the campaign period is currently open.
     *
     * @param  \App\Models\ElectionCycle|null  $cycle
     * @return bool
     */
    public function isCampaignOpen(?ElectionCycle $cycle): bool
    {
        if (!$cycle || $cycle->status !== 'active') {
            return false;
        }
        return now()->between($cycle->campaign_start, $cycle->campaign_end);
    }

    /**
     * Check if the student has already voted.
     *
     * @param  mixed  $student
     * @return bool
     */
    public function hasStudentVoted($student): bool
    {
        return $student && $student->has_voted;
    }

    /**
     * Retrieve positions and their candidates for the voter's department.
     *
     * Only returns positions that have approved/active candidates
     * and are within the voter's course department.
     *
     * @param  \App\Models\ElectionCycle|null  $activeCycle
     * @param  bool  $isVotingOpen
     * @param  int  $voterCourseId
     * @return \Illuminate\Support\Collection
     */
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
                $query->whereHas('student', fn($q) => $q->where('course_id', $voterCourseId));
            })
            ->with([
                'candidates' => function ($query) use ($voterCourseId) {
                    $query->with('student');
                },
            ])
            ->orderBy('priority', 'asc')
            ->get();
    }

    /**
     * Get the course ID for the currently authenticated voter.
     *
     * @return int|null
     */
    public function getVoterCourseId(): ?int
    {
        return Auth::user()->student->course_id ?? null;
    }

    /**
     * Submit the student's vote selections.
     *
     * Uses a database transaction with row-level locking to prevent
     * duplicate submissions. Generates a unique reference number,
     * logs the activity, sends a confirmation email, and broadcasts
     * a real-time event.
     *
     * @param  \App\Models\User  $user
     * @param  array  $selections
     * @param  \App\Models\ElectionCycle  $cycle
     * @return array
     */
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

    /**
     * Invalidate the tally cache for a cycle and course.
     *
     * @param  \App\Models\ElectionCycle  $cycle
     * @param  int|null  $courseId
     * @return void
     */
    private function forgetTallyCache(ElectionCycle $cycle, ?int $courseId): void
    {
        $cacheKey = "tally_data_cycle_{$cycle->id}_course_{$courseId}";
        Cache::forget($cacheKey);
    }

    /**
     * Generate a deterministic avatar color based on candidate ID.
     *
     * @param  int  $id
     * @return string
     */
    public function getAvatarColor(int $id): string
    {
        $colors = ['#10b981', '#3b82f6', '#6366f1', '#f59e0b', '#ef4444'];
        return $colors[$id % count($colors)];
    }
}

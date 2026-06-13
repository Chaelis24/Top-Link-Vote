<?php

namespace App\Services\Student;

use App\Models\{Setting, ElectionCycle, Candidate, User};
use Illuminate\Support\Facades\Cache;

/**
 * Service for managing the student dashboard data.
 *
 * Handles student profile retrieval, voting status checks,
 * election-cycle interactions, and tally data caching.
 */
class DashboardService
{
    /**
     * Load the authenticated student's profile and course affiliation.
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    public function getStudentData(User $user): array
    {
        $user->load('student.block.course');
        $student = $user->student;

        return [
            'student' => $student,
            'studentCourse' => $student?->block?->course?->name ?? 'No Course Assigned',
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
     * Checks both the admin-controlled "allowVoting" setting
     * and whether the current date falls within the voting window.
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
     * Check whether election results are visible to students.
     *
     * @return bool
     */
    public function isResultsVisible(): bool
    {
        return (bool) (Setting::where('key', 'showResults')->value('value') ?? false);
    }

    /**
     * Fetch and cache the vote tally for the student's department.
     *
     * Results are cached for 10 minutes to reduce database load.
     *
     * @param  \App\Models\ElectionCycle|null  $activeCycle
     * @param  int|null  $studentCourseId
     * @param  bool  $isResultsVisible
     * @return array
     */
    public function getTallyData(?ElectionCycle $activeCycle, ?int $studentCourseId, bool $isResultsVisible): array
    {
        if (!$isResultsVisible || !$activeCycle) {
            return [];
        }

        $cacheKey = "tally_data_cycle_{$activeCycle->id}_course_{$studentCourseId}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($activeCycle, $studentCourseId) {
            return Candidate::query()
                ->with(['student.block.course', 'position'])
                ->withCount(['votes' => fn($q) => $q->where('election_cycle_id', $activeCycle->id)])
                ->where('candidates.election_cycle_id', $activeCycle->id)
                ->whereHas('position', function ($query) use ($studentCourseId) {
                    $query->where(function ($q) use ($studentCourseId) {
                        $q->whereNull('student_department')
                            ->orWhere('student_department', '')
                            ->orWhere('student_department', $studentCourseId);
                    });
                })
                ->get()
                ->map(fn($candidate) => [
                    'label' => ($candidate->student->last_name ?? 'Unknown') . ' (' . ($candidate->position->name ?? 'N/A') . ')',
                    'votes' => (int) ($candidate->votes_count ?? 0),
                ])
                ->values()
                ->toArray();
        });
    }

    /**
     * Get the most recently created election cycle.
     *
     * @return \App\Models\ElectionCycle|null
     */
    public function getLatestCycle(): ?ElectionCycle
    {
        return ElectionCycle::latest()->first();
    }

    /**
     * Resolve a short course code into its full display name.
     *
     * @param  string|null  $courseName
     * @return string
     */
    public function getCourseDisplayName(?string $courseName): string
    {
        return match ($courseName) {
            'IT'    => 'Information Technology',
            'HRMT'  => 'Hotel and Restaurant Management',
            'HST'   => 'Hospitality Service Technology',
            'ECT'   => 'Electronic Computer Technology',
            default => $courseName ?? 'General',
        };
    }

    /**
     * Invalidate the cached tally data for a given cycle and course.
     *
     * @param  \App\Models\ElectionCycle  $activeCycle
     * @param  int|null  $studentCourseId
     * @return void
     */
    public function forgetTallyCache(ElectionCycle $activeCycle, ?int $studentCourseId): void
    {
        $cacheKey = "tally_data_cycle_{$activeCycle->id}_course_{$studentCourseId}";
        Cache::forget($cacheKey);
    }

    /**
     * Retrieve the course ID associated with the student's block.
     *
     * @param  mixed  $student
     * @return int|null
     */
    public function getStudentCourseId($student): ?int
    {
        return $student?->block?->course_id;
    }
}

<?php

namespace App\Services\Student;

use App\Models\{Setting, ElectionCycle, Candidate, User};
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function getStudentData(User $user): array
    {
        $user->load('student.block.course');
        $student = $user->student;

        return [
            'student' => $student,
            'studentCourse' => $student?->block?->course?->name ?? 'No Course Assigned',
            'profile_photo_path' => $student?->photo ?? ($student?->profile_photo_path ?? null),
        ];
    }

    public function getActiveCycle(): ?ElectionCycle
    {
        return ElectionCycle::where('status', 'active')
            ->where('voting_start', '<=', now())
            ->where('voting_end', '>=', now())
            ->first();
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

    public function isResultsVisible(): bool
    {
        return (bool) (Setting::where('key', 'showResults')->value('value') ?? false);
    }

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

    public function getLatestCycle(): ?ElectionCycle
    {
        return ElectionCycle::latest()->first();
    }

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

    public function forgetTallyCache(ElectionCycle $activeCycle, ?int $studentCourseId): void
    {
        $cacheKey = "tally_data_cycle_{$activeCycle->id}_course_{$studentCourseId}";
        Cache::forget($cacheKey);
    }

    public function getStudentCourseId($student): ?int
    {
        return $student?->block?->course_id;
    }
}

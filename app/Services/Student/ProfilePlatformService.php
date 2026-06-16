<?php

namespace App\Services\Student;

use App\Models\{ElectionCycle, Candidate, Position, Platform, User};
use Illuminate\Support\Collection;

/**
 * Service for candidate profile and platform management.
 *
 * Handles viewing candidate profiles, determining filing/campaign/voting
 * phase status, and providing filtered candidate data for students.
 */
class ProfilePlatformService
{
    /**
     * Load the authenticated student's data along with any candidate relation.
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    public function getStudentData(User $user): array
    {
        $user->load('student', 'candidate');
        $student = $user->student;

        return [
            'student' => $student,
            'profile_photo_path' => $student?->photo ?? '',
        ];
    }

    /**
     * Load the current candidate's profile and platform data for editing.
     *
     * Normalizes achievements, previous positions, and projects into
     * arrays suitable for Livewire form binding.
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    public function loadCandidateData(User $user): array
    {
        $candidate = $user->candidate;
        if (!$candidate) {
            return [];
        }

        $platform = Platform::where('candidate_id', $candidate->id)->first();

        return [
            'achievements' => $candidate->achievements
                ? (is_array($candidate->achievements) ? $candidate->achievements : explode("\n", $candidate->achievements))
                : [''],
            'party_name' => $candidate->party_name ?? '',
            'previous_position' => is_array($candidate->previous_position) ? $candidate->previous_position : [''],
            'previous_school_project' => is_array($candidate->previous_school_project) ? $candidate->previous_school_project : [''],
            'average_grade' => $candidate->average_grade ?? '',
            'existing_candidate_photo' => $candidate->photo,
            'platform_title' => $platform->title ?? '',
            'tagline' => $platform->tagline ?? '',
            'agenda' => $platform ? (is_array($platform->agenda) ? implode("\n", $platform->agenda) : $platform->agenda ?? '') : '',
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
     * Check whether the voting period is currently open.
     *
     * @param  \App\Models\ElectionCycle|null  $cycle
     * @return bool
     */
    public function isVotingOpen(?ElectionCycle $cycle): bool
    {
        if (!$cycle || $cycle->status !== 'active') {
            return false;
        }
        return now()->between($cycle->voting_start, $cycle->voting_end);
    }

    /**
     * Get the list of positions that have approved candidates for the voter's department.
     *
     * Prepend "All Positions" as the default filter option.
     *
     * @param  \App\Models\ElectionCycle|null  $activeCycle
     * @param  int|null  $voterCourseId
     * @return \Illuminate\Support\Collection
     */
    public function getPositionsList(?ElectionCycle $activeCycle, ?int $voterCourseId): Collection
    {
        if (!$activeCycle) {
            return collect(['All Positions']);
        }

        $positions = Position::where('election_cycle_id', $activeCycle->id)
            ->where(function ($query) use ($voterCourseId) {
                $query->whereNull('student_department')
                    ->orWhere('student_department', '')
                    ->orWhere('student_department', $voterCourseId);
            })
            ->whereHas('candidates')
            ->pluck('name')
            ->unique();

        return collect(['All Positions'])->concat($positions);
    }

    /**
     * Get approved or active candidates filtered by position.
     *
     * Filters candidates by the voter's department and optionally
     * by the selected position name.
     *
     * @param  \App\Models\ElectionCycle|null  $activeCycle
     * @param  int|null  $voterCourseId
     * @param  string  $selectedPosition
     * @return \Illuminate\Support\Collection
     */
    public function getFilteredCandidates(?ElectionCycle $activeCycle, ?int $voterCourseId, string $selectedPosition): Collection
    {
        if (!$activeCycle) {
            return collect();
        }

        return Candidate::with(['student.user', 'position', 'platforms' => fn($q) => $q->latest()])
            ->where('election_cycle_id', $activeCycle->id)
            ->whereHas('position', function ($q) use ($voterCourseId) {
                $q->where(fn($sub) => $sub->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourseId));
            })
            ->when($selectedPosition !== 'All Positions', fn($query) => $query->whereHas('position', fn($q) => $q->where('name', $selectedPosition)))
            ->get();
    }

    /**
     * Get the course ID associated with the student.
     *
     * @param  mixed  $student
     * @return int|null
     */
    public function getStudentCourseId($student): ?int
    {
        return $student?->course_id ?? null;
    }

    /**
     * Generate a deterministic avatar color based on candidate ID.
     *
     * @param  int  $id
     * @return string
     */
    public function getAvatarColor(int $id): string
    {
        $colors = ['#10b981', '#1976D2', '#D32F2F', '#FBC02D', '#8E24AA', '#E64A19'];
        return $colors[$id % count($colors)];
    }
}

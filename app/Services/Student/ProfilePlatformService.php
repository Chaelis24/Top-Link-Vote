<?php

namespace App\Services\Student;

use App\Models\{ElectionCycle, Candidate, Position, Platform, User};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class ProfilePlatformService
{
    public function getStudentData(User $user): array
    {
        $user->load('student', 'candidate');
        $student = $user->student;

        return [
            'student' => $student,
            'profile_photo_path' => $student?->photo ?? '',
        ];
    }

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

    public function getActiveCycle(): ?ElectionCycle
    {
        return ElectionCycle::where('status', 'active')->first();
    }

    public function isEligibleToEdit(): bool
    {
        $user = Auth::user();
        return $user && $user->hasRole('candidate') && $user->candidate;
    }

    public function isFilingOpen(?ElectionCycle $cycle): bool
    {
        if (!$cycle || $cycle->status !== 'active') {
            return false;
        }
        return now()->between($cycle->filing_start, $cycle->filing_end);
    }

    public function isCampaignOpen(?ElectionCycle $cycle): bool
    {
        if (!$cycle || $cycle->status !== 'active') {
            return false;
        }
        return now()->between($cycle->campaign_start, $cycle->campaign_end);
    }

    public function isVotingOpen(?ElectionCycle $cycle): bool
    {
        if (!$cycle || $cycle->status !== 'active') {
            return false;
        }
        return now()->between($cycle->voting_start, $cycle->voting_end);
    }

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
            ->whereHas('candidates', fn($q) => $q->whereIn('status', ['approved', 'active']))
            ->pluck('name')
            ->unique();

        return collect(['All Positions'])->concat($positions);
    }

    public function getFilteredCandidates(?ElectionCycle $activeCycle, ?int $voterCourseId, string $selectedPosition): Collection
    {
        if (!$activeCycle) {
            return collect();
        }

        return Candidate::with(['student.user', 'position', 'platforms' => fn($q) => $q->latest()])
            ->where('election_cycle_id', $activeCycle->id)
            ->whereIn('status', ['approved', 'active'])
            ->whereHas('position', function ($q) use ($voterCourseId) {
                $q->where(fn($sub) => $sub->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourseId));
            })
            ->when($selectedPosition !== 'All Positions', fn($query) => $query->whereHas('position', fn($q) => $q->where('name', $selectedPosition)))
            ->get();
    }

    public function getStudentCourseId($student): ?int
    {
        return $student?->course_id ?? null;
    }

    public function getAvatarColor(int $id): string
    {
        $colors = ['#10b981', '#1976D2', '#D32F2F', '#FBC02D', '#8E24AA', '#E64A19'];
        return $colors[$id % count($colors)];
    }
}

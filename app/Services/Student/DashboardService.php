<?php

namespace App\Services\Student;

use App\Models\{Setting, ElectionCycle, Candidate};
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function getElectionSettings(): array
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        return [
            'allowVoting' => (bool) ($settings['allowVoting'] ?? false),
            'showResults' => (bool) ($settings['showResults'] ?? false),
        ];
    }

    public function isVotingOpen(): bool
    {
        $setting = Setting::where('key', 'allowVoting')->first();
        $activeCycle = ElectionCycle::where('status', 'active')->latest()->first();

        if (!$setting || !(bool) $setting->value) return false;
        if (!$activeCycle || now()->gt($activeCycle->voting_end)) return false;

        return true;
    }

    public function getTallyData(?string $course, bool $isVisible): array
    {
        if (!$isVisible) return [];

        return Cache::remember('tally_results_' . ($course ?? 'all'), 60, function () use ($course) {
            return Candidate::with(['student', 'position'])
                ->withCount('votes')
                ->whereHas('position', fn($q) => $q->whereNull('student_department')
                    ->orWhere('student_department', '')
                    ->orWhere('student_department', $course))
                ->join('positions', 'candidates.position_id', '=', 'positions.id')
                ->orderBy('positions.priority', 'asc')
                ->orderBy('votes_count', 'desc')
                ->select('candidates.*')
                ->get()
                ->map(fn($c) => [
                    'label' => ($c->student->last_name ?? 'Unknown') . ' (' . ($c->position->name ?? 'N/A') . ')',
                    'votes' => $c->votes_count ?? 0,
                ])->toArray();
        });
    }

    public function forgetTallyCache(?string $course)
    {
        Cache::forget('tally_results_' . ($course ?? 'all'));
    }
}

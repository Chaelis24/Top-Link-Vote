<?php

use function Livewire\Volt\{state, layout, title, computed};

layout('layouts.app');
title('Profiles & Candidates');

state([
    'search' => '',
    'selectedPosition' => 'All Positions',
    'positions' => ['All Positions', 'President', 'Vice President', 'Secretary', 'Treasurer'],

    // Sample Data (Sa production, ito ay Candidate::with('party')->get())
    'candidates' => [
        [
            'id' => 1,
            'name' => 'Maria Santos',
            'position' => 'President',
            'party' => 'Unity Party',
            'party_class' => 'party-green',
            'initials' => 'MS',
            'color' => '#388E3C',
            'motto' => 'Building a stronger, more inclusive student body through transparency and innovation.',
            'gpa' => '4.8',
            'year' => '3rd',
            'course' => 'BSIT',
        ],
        [
            'id' => 2,
            'name' => 'Juan Dela Cruz',
            'position' => 'President',
            'party' => 'Progress Alliance',
            'party_class' => 'party-purple',
            'initials' => 'JD',
            'color' => '#673AB7',
            'motto' => 'Championing academic excellence and student welfare for a brighter tomorrow.',
            'gpa' => '4.5',
            'year' => '4th',
            'course' => 'BSCS',
        ],
        [
            'id' => 5,
            'name' => 'Patricia Lim',
            'position' => 'Treasurer',
            'party' => 'Independent',
            'party_class' => 'party-gold',
            'initials' => 'PL',
            'color' => '#fdcb6e',
            'motto' => 'Financial transparency and responsible budgeting for every student org.',
            'gpa' => '4.7',
            'year' => '3rd',
            'course' => 'BSA',
        ],
    ],
]);

// Reactive filtering logic
$filteredCandidates = computed(function () {
    return collect($this->candidates)->filter(function ($candidate) {
        $matchesSearch = empty($this->search) || str_contains(strtolower($candidate['name']), strtolower($this->search)) || str_contains(strtolower($candidate['party']), strtolower($this->search));

        $matchesPosition = $this->selectedPosition === 'All Positions' || $candidate['position'] === $this->selectedPosition;

        return $matchesSearch && $matchesPosition;
    });
});

$logout = function () {
    Auth::guard('web')->logout();
    Session::invalidate();
    Session::regenerateToken();
    return $this->redirect('/', navigate: true);
};

?>

<div>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.student-sidebar')

    <main class="main-content">
        {{-- Top Bar --}}
        <div class="topbar" data-aos="fade-down">
            <div>
                <h2>Profiles & <span>Candidates</span></h2>
                <p class="text-white-50 mb-0">Meet the candidates running for student council</p>
            </div>
            <a href="/students/profile" wire:navigate class="text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-circle">
                            <i class="bi bi-person-fill text-white"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Search & Filter Bar --}}
        <div class="search-filter-bar mb-4" data-aos="fade-up">
            <div class="row g-3 align-items-center">
                <div class="col-lg-5">
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute"
                            style="left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                        <input type="text" wire:model.live.debounce.300ms="search"
                            class="form-control search-input w-100" placeholder="Search name or party...">
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach ($positions as $pos)
                            <button wire:click="$set('selectedPosition', '{{ $pos }}')"
                                class="filter-btn {{ $selectedPosition === $pos ? 'active' : '' }}">
                                {{ $pos }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Candidates Grid --}}
        <div class="row g-4">
            @forelse($this->filteredCandidates as $candidate)
                <div class="col-lg-4 col-md-6" wire:key="cand-{{ $candidate['id'] }}" data-aos="zoom-in">
                    <div class="glass-card candidate-card p-4 h-100 text-center">
                        <div class="mb-3 position-relative d-inline-block">
                            <div class="candidate-avatar d-flex align-items-center justify-content-center fw-bold text-white fs-2"
                                style="background: {{ $candidate['color'] }}; width: 100px; height: 100px; border-radius: 50%; margin: 0 auto; border: 4px solid rgba(255,255,255,0.1);">
                                {{ $candidate['initials'] }}
                            </div>
                        </div>

                        <h5 class="fw-bold mb-1 text-white">{{ $candidate['name'] }}</h5>
                        <span class="candidate-position mb-2 d-block">
                            <i class="bi bi-star-fill me-1 text-warning" style="font-size: 0.7rem;"></i>
                            {{ $candidate['position'] }}
                        </span>

                        <div class="my-2">
                            <span
                                class="candidate-party {{ $candidate['party_class'] }}">{{ $candidate['party'] }}</span>
                        </div>

                        <p class="text-white-50 mb-3 small" style="min-height: 40px;">"{{ $candidate['motto'] }}"</p>

                        <div class="candidate-stats mb-4">
                            <div class="stat">
                                <div class="stat-num">{{ $candidate['gpa'] }}</div>
                                <div class="stat-label">GPA</div>
                            </div>
                            <div class="stat">
                                <div class="stat-num">{{ $candidate['year'] }}</div>
                                <div class="stat-label">Year</div>
                            </div>
                            <div class="stat">
                                <div class="stat-num">{{ $candidate['course'] }}</div>
                                <div class="stat-label">Course</div>
                            </div>
                        </div>

                        <button class="btn btn-outline-glow btn-sm w-100" data-bs-toggle="modal"
                            data-bs-target="#viewProfileModal-{{ $candidate['id'] }}">
                            <i class="bi bi-eye me-1"></i>View Full Profile
                        </button>
                    </div>
                </div>

                {{-- Modal per Candidate --}}
                <div class="modal fade" id="viewProfileModal-{{ $candidate['id'] }}" tabindex="-1" wire:ignore.self>
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-white-10" style="background: #1a1a2e;">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-bold text-white"><i
                                        class="bi bi-person-lines-fill me-2 text-accent"></i>Candidate Profile</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <div class="candidate-avatar d-flex align-items-center justify-content-center fw-bold text-white fs-1 mx-auto mb-3"
                                    style="background: {{ $candidate['color'] }}; width: 120px; height: 120px; border-radius: 50%; border: 4px solid var(--accent);">
                                    {{ $candidate['initials'] }}
                                </div>
                                <h4 class="fw-bold mb-1 text-white">{{ $candidate['name'] }}</h4>
                                <span
                                    class="badge bg-accent bg-opacity-20 text-accent rounded-pill px-3 py-2 mb-4">{{ $candidate['party'] }}</span>

                                <div class="row text-start g-3">
                                    <div class="col-md-6">
                                        <div class="glass p-3 rounded-3 border-white-5">
                                            <h6 class="small text-accent fw-bold mb-3">Academic Info</h6>
                                            <div
                                                class="d-flex justify-content-between small mb-2 border-bottom border-white-5 pb-1">
                                                <span class="text-white-50">Course</span><span
                                                    class="text-white">{{ $candidate['course'] }}</span>
                                            </div>
                                            <div
                                                class="d-flex justify-content-between small mb-2 border-bottom border-white-5 pb-1">
                                                <span class="text-white-50">GPA</span><span
                                                    class="text-accent fw-bold">{{ $candidate['gpa'] }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="glass p-3 rounded-3 border-white-5">
                                            <h6 class="small text-warning fw-bold mb-3">Motto</h6>
                                            <p class="small text-white-50 fst-italic mb-0">"{{ $candidate['motto'] }}"
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-white-10">
                                <button type="button" class="btn btn-outline-glow btn-sm"
                                    data-bs-dismiss="modal">Close</button>
                                <a href="/student/vote" wire:navigate class="btn btn-glow btn-sm">Vote for Candidate</a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5" data-aos="fade-in">
                    <i class="bi bi-search fs-1 text-white-50"></i>
                    <p class="text-white-50 mt-3">No candidates found matching your search.</p>
                </div>
            @endforelse
        </div>
    </main>

    <style>
        .border-white-5 {
            border-color: rgba(255, 255, 255, 0.05) !important;
        }

        .border-white-10 {
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
    </style>
</div>

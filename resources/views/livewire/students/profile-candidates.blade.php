<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Illuminate\Support\Facades\{Auth, Session};
use App\Models\{Candidate, Position, ElectionCycle};

new #[Layout('layouts.app')] #[Title('Profiles & Candidates')] class extends Component {
    use WithFileUploads;

    #[Url]
    public string $search = '';

    #[Url]
    public string $selectedPosition = 'All Positions';

    public $student;
    public $profile_photo_path;
    public $photo;

    public function mount()
    {
        $user = Auth::user()?->load('student');
        $this->student = $user?->student;

        if ($this->student) {
            $this->profile_photo_path = $this->student->photo ?? ($this->student->profile_photo_path ?? null);
        }
    }

    #[Computed]
    public function positionsList()
    {
        $voterCourse = $this->student->course ?? '';
        $activeCycle = ElectionCycle::where('status', 'active')->first();

        if (!$activeCycle) {
            return collect(['All Positions']);
        }

        return collect(['All Positions'])->concat(
            Position::where('election_cycle_id', $activeCycle->id)
                ->where(function ($query) use ($voterCourse) {
                    $query->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourse);
                })
                ->whereHas('candidates', function ($q) {
                    $q->whereIn('status', ['approved', 'active']);
                })
                ->pluck('name')
                ->unique(),
        );
    }

    #[Computed]
    public function filteredCandidates()
    {
        $voterCourse = $this->student->course ?? '';
        $activeCycle = ElectionCycle::where('status', 'active')->first();

        if (!$activeCycle) {
            return collect();
        }

        return Candidate::with(['student.user', 'position'])
            ->where('election_cycle_id', $activeCycle->id)
            ->whereIn('status', ['approved', 'active'])
            ->whereHas('position', function ($query) use ($voterCourse) {
                $query->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourse);
            })
            ->when($this->selectedPosition !== 'All Positions', function ($query) {
                $query->whereHas('position', fn($q) => $q->where('name', $this->selectedPosition));
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('student', function ($sq) {
                        $sq->where('first_name', 'like', '%' . $this->search . '%')->orWhere('last_name', 'like', '%' . $this->search . '%');
                    })->orWhere('party_name', 'like', '%' . $this->search . '%');
                });
            })
            ->get();
    }

    public function getAvatarColor($id)
    {
        $colors = ['#388E3C', '#1976D2', '#D32F2F', '#FBC02D', '#8E24AA', '#E64A19'];
        return $colors[$id % count($colors)];
    }

    public function getPartyClass($id)
    {
        $classes = ['party-green', 'party-blue', 'party-red', 'party-warning', 'party-purple'];
        return $classes[$id % count($classes)];
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.student-sidebar')

    <main class="main-content">
        <div class="topbar" wire:key="persistent-topbar-header">
            <div>
                <h2>Profiles & <span>Candidates</span></h2>
                <p class="text-white-50 mb-0">Meet the candidates running for student council</p>
            </div>
            <a href="/students/profile" wire:navigate class="text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-circle overflow-hidden">
                        @if ($photo ?? '')
                            <img src="{{ $photo->temporaryUrl() }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @elseif($profile_photo_path ?? '')
                            <img src="{{ asset('storage/' . $profile_photo_path) }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <i class="bi bi-person-fill text-white"></i>
                        @endif
                    </div>
                </div>
            </a>
        </div>

        <div class="search-filter-bar mb-4 fade-in-up delay-1">
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
                        @foreach ($this->positionsList as $pos)
                            <button wire:click="$set('selectedPosition', '{{ $pos }}')"
                                class="filter-btn {{ $selectedPosition === $pos ? 'active' : '' }}">
                                {{ $pos }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4">
            @forelse($this->filteredCandidates as $candidate)
                <div class="col-lg-4 col-md-6 fade-in-up delay-2" wire:key="cand-card-{{ $candidate->id }}">
                    <div class="glass-card candidate-card p-4 h-100 text-center">
                        <div class="mb-3 position-relative d-inline-block">
                            <div class="candidate-avatar d-flex align-items-center justify-content-center fw-bold text-white fs-2"
                                style="background: {{ $this->getAvatarColor($candidate->id) }}; width: 100px; height: 100px; border-radius: 50%; margin: 0 auto; border: 4px solid rgba(255,255,255,0.1); overflow: hidden;">
                                @if ($candidate->student->profile_photo_path)
                                    <img src="{{ asset('storage/' . $candidate->student->profile_photo_path) }}"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                @else
                                    {{ strtoupper(substr($candidate->student->first_name, 0, 1)) }}{{ strtoupper(substr($candidate->student->last_name, 0, 1)) }}
                                @endif
                            </div>
                        </div>

                        <h5 class="fw-bold mb-1 text-white">{{ $candidate->student->first_name }}
                            {{ $candidate->student->last_name }}</h5>
                        <span class="candidate-position mb-2 d-block text-accent">
                            <i class="bi bi-star-fill me-1 text-warning" style="font-size: 0.7rem;"></i>
                            {{ $candidate->position->name }}
                        </span>

                        <div class="my-2">
                            <span
                                class="candidate-party {{ $this->getPartyClass($candidate->id) }}">{{ $candidate->party_name ?? 'Independent' }}</span>
                        </div>

                        <p class="text-white-50 mb-3 small" style="min-height: 40px;">
                            "{{ Str::limit($candidate->slogan ?? 'Ready to serve the student body.', 60) }}"
                        </p>

                        <button type="button" class="btn btn-outline-glow btn-sm w-100" data-bs-toggle="modal"
                            data-bs-target="#profileModal{{ $candidate->id }}">
                            <i class="bi bi-person-lines-fill me-1"></i>View Full Profile
                        </button>
                    </div>
                </div>

                <div class="modal fade" id="profileModal{{ $candidate->id }}" tabindex="-1" aria-hidden="true"
                    wire:ignore.self>
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content border-white-10 shadow-lg"
                            style="background: #1a1a2e; border-radius: 20px;">
                            <div class="modal-header border-0 pb-0">
                                <h5 class="modal-title fw-bold text-white">Candidate Details</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-4">
                                <div class="text-center mb-4">
                                    <div class="candidate-avatar d-flex align-items-center justify-content-center fw-bold text-white fs-1 mx-auto mb-3 shadow"
                                        style="background: {{ $this->getAvatarColor($candidate->id) }}; width: 120px; height: 120px; border-radius: 50%; border: 4px solid var(--accent); overflow: hidden;">
                                        @if ($candidate->student->profile_photo_path)
                                            <img src="{{ asset('storage/' . $candidate->student->profile_photo_path) }}"
                                                style="width: 100%; height: 100%; object-fit: cover;">
                                        @else
                                            {{ strtoupper(substr($candidate->student->first_name, 0, 1)) }}{{ strtoupper(substr($candidate->student->last_name, 0, 1)) }}
                                        @endif
                                    </div>
                                    <h3 class="fw-bold mb-1 text-white">{{ $candidate->student->first_name }}
                                        {{ $candidate->student->last_name }}</h3>
                                    <span
                                        class="badge bg-accent bg-opacity-25 text-accent border border-accent border-opacity-25 px-3 py-2">{{ $candidate->party_name ?? 'Independent' }}</span>
                                </div>

                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <div class="glass p-3 rounded-4 border-white-5 h-100">
                                            <h6 class="small text-accent fw-bold mb-3 text-uppercase">Academic Info</h6>
                                            <div
                                                class="d-flex justify-content-between mb-2 border-bottom border-white-5 pb-2">
                                                <span class="text-white-50 small">Course</span>
                                                <span
                                                    class="text-white small fw-bold">{{ $candidate->student->course ?? 'N/A' }}</span>
                                            </div>
                                            <div
                                                class="d-flex justify-content-between mb-2 border-bottom border-white-5 pb-2">
                                                <span class="text-white-50 small">Year</span>
                                                <span
                                                    class="text-white small fw-bold">{{ $candidate->student->year_level ?? 'N/A' }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span class="text-white-50 small">GPA</span>
                                                <span
                                                    class="text-success small fw-bold">{{ $candidate->student->gpa ?? 'N/A' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-7 text-start">
                                        <div class="glass p-3 rounded-4 border-white-5 h-100">
                                            <h6 class="small text-warning fw-bold mb-2 text-uppercase">Motto</h6>
                                            <p class="text-white small fst-italic mb-3">
                                                "{{ $candidate->slogan ?? 'Ready to serve.' }}"</p>

                                            <h6 class="small text-accent fw-bold mb-2 text-uppercase">Full Bio &
                                                Manifesto</h6>
                                            <div
                                                style="max-height: 150px; overflow-y: auto; scrollbar-width: thin; text-align: left;">
                                                <p class="text-white-50 mb-0 small"
                                                    style="white-space: line-height: 1.5; text-align: left;">
                                                    {{ $candidate->bio ?? 'No additional details provided.' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer border-white-10">
                                <button type="button" class="btn btn-outline-glow btn-sm"
                                    data-bs-dismiss="modal">Close</button>
                                <a href="/students/cast-vote" wire:navigate class="btn btn-glow btn-sm"
                                    data-bs-dismiss="modal">Proceed to Vote</a>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5">
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

        .modal-backdrop {
            opacity: 0.8 !important;
            backdrop-filter: blur(5px);
        }

        .btn-outline-glow {
            position: relative;
            z-index: 5;
        }
    </style>
</div>

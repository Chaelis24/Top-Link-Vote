<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Illuminate\Support\Facades\{Auth, Session, Storage, Log};
use Illuminate\Support\Str;
use App\Models\{Candidate, Position, ElectionCycle, Platform, Setting};

new #[Layout('layouts.app')] #[Title('Profiles and Platforms')] class extends Component {
    use WithFileUploads;

    #[Url]
    public string $selectedPosition = 'All Positions';

    public bool $showProfiles = false;
    public bool $isVotingOpen = false;
    public bool $lockChanges = false;

    public string $party_name = '';
    public $achievements = [];
    public array $previous_position = [''];
    public array $previous_school_project = [''];
    public string $average_grade = '';
    public $candidate_photo;
    public $existing_candidate_photo;

    public string $platform_title = '';
    public string $tagline = '';
    public string $agenda = '';

    public $profile_photo_path = '';
    public $student;

    #[Computed]
    public function isEligibleToEdit()
    {
        $user = Auth::user();
        return $user && $user->hasRole('Candidate') && $user->candidate;
    }

    #[Computed]
    public function positionsList()
    {
        $voterCourse = $this->student->course ?? '';
        $activeCycle = ElectionCycle::where('status', 'active')->first();

        if (!$activeCycle) {
            return collect(['All Positions']);
        }

        $positions = Position::where('election_cycle_id', $activeCycle->id)
            ->where(function ($query) use ($voterCourse) {
                $query->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourse);
            })
            ->whereHas('candidates', function ($q) {
                $q->whereIn('status', ['approved', 'active']);
            })
            ->pluck('name')
            ->unique();

        return collect(['All Positions'])->concat($positions);
    }

    #[Computed]
    public function filteredCandidates()
    {
        $voterCourse = $this->student->course ?? '';
        $activeCycle = ElectionCycle::where('status', 'active')->first();

        if (!$activeCycle) {
            return collect();
        }

        return Candidate::with(['student.user', 'position', 'platforms' => fn($q) => $q->latest()])
            ->where('election_cycle_id', $activeCycle->id)
            ->whereIn('status', ['approved', 'active'])
            ->where(function ($query) use ($voterCourse) {
                $query->whereHas('position', function ($q) use ($voterCourse) {
                    $q->where(function ($sub) use ($voterCourse) {
                        $sub->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourse);
                    });
                });
            })
            ->where(function ($query) use ($voterCourse) {
                $query
                    ->whereHas('position', function ($q) {
                        $q->whereNull('student_department')->orWhere('student_department', '');
                    })
                    ->orWhereHas('student', function ($q) use ($voterCourse) {
                        $q->where('course', $voterCourse);
                    });
            })
            ->when($this->selectedPosition !== 'All Positions', function ($query) {
                $query->whereHas('position', fn($q) => $q->where('name', $this->selectedPosition));
            })
            ->get();
    }

    public function mount()
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $this->isVotingOpen = (bool) ($settings['allowVoting'] ?? false);
        $this->showProfiles = (bool) ($settings['showProfiles'] ?? false);
        $this->lockChanges = (bool) ($settings['lockChanges'] ?? false);

        $user = Auth::user()?->load('student', 'candidate');
        $this->student = $user?->student;

        if ($this->student) {
            $this->profile_photo_path = $this->student->photo;
        }

        if ($this->isEligibleToEdit) {
            $candidate = Auth::user()->candidate;

            if ($candidate && $candidate->achievements) {
                $this->achievements = is_array($candidate->achievements) ? $candidate->achievements : explode("\n", $candidate->achievements);
            } else {
                $this->achievements = [''];
            }

            $this->party_name = $candidate->party_name ?? '';
            $this->previous_position = is_array($candidate->previous_position) ? $candidate->previous_position : [''];
            $this->previous_school_project = is_array($candidate->previous_school_project) ? $candidate->previous_school_project : [''];
            $this->average_grade = $candidate->average_grade ?? '';
            $this->existing_candidate_photo = $candidate->photo;

            $platform = Platform::where('candidate_id', $candidate->id)->first();
            if ($platform) {
                $this->platform_title = $platform->title ?? '';
                $this->tagline = $platform->tagline ?? '';
                $this->agenda = is_array($platform->agenda) ? implode("\n", $platform->agenda) : $platform->agenda ?? '';
            }
        }
    }

    public function addField($property)
    {
        $this->{$property}[] = '';
    }

    public function removeField($property, $index)
    {
        unset($this->{$property}[$index]);
        $this->{$property} = array_values($this->{$property});
    }

    public function updatePlatform()
    {
        if (!$this->isEligibleToEdit || $this->lockChanges) {
            $this->dispatch('swal', [
                'title' => 'Changes Locked',
                'text' => 'The administrator has frozen all candidate profile updates.',
                'icon' => 'warning',
            ]);
            return;
        }

        $this->validate([
            'party_name' => 'nullable|string|max:255',
            'tagline' => 'required|string|max:255',
            'platform_title' => 'required|string|max:255',
            'agenda' => 'required|string',
            'average_grade' => 'nullable|numeric',
            'candidate_photo' => 'nullable|image|max:2048',
        ]);

        $tempAchievements = is_array($this->achievements) ? implode("\n", $this->achievements) : (string) $this->achievements;

        $agendaArray = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $this->agenda)))));
        $achievementsArray = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $tempAchievements)))));

        try {
            $candidate = Auth::user()->candidate;

            if ($this->candidate_photo) {
                if ($candidate->photo) {
                    Storage::disk('public')->delete($candidate->photo);
                }
                $photoPath = $this->candidate_photo->store('candidates-picture', 'public');
            } else {
                $photoPath = $candidate->photo;
            }

            $candidate->update([
                'party_name' => $this->party_name,
                'achievements' => implode("\n", $achievementsArray),
                'average_grade' => $this->average_grade,
                'previous_position' => $this->previous_position ?: [],
                'previous_school_project' => $this->previous_school_project ?: [],
                'photo' => $photoPath,
            ]);

            $this->achievements = $achievementsArray;

            Platform::updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'title' => $this->platform_title,
                    'tagline' => $this->tagline,
                    'agenda' => $agendaArray,
                    'status' => 'pending',
                    'submitted_at' => now(),
                ],
            );

            $this->dispatch('swal', [
                'title' => 'Submission Successful!',
                'text' => 'Your profile and platform have been submitted for review.',
                'icon' => 'success',
            ]);

            $this->dispatch('close-modal');
        } catch (\Exception $e) {
            Log::error('Update Error: ' . $e->getMessage());
            $this->dispatch('swal', [
                'title' => 'Error',
                'text' => 'Something went wrong while submitting your platform.',
                'icon' => 'error',
            ]);
        }
    }

    public function selectPosition($pos)
    {
        $this->selectedPosition = $pos;
    }

    public function getAvatarColor($id)
    {
        $colors = ['#10b981', '#1976D2', '#D32F2F', '#FBC02D', '#8E24AA', '#E64A19'];
        return $colors[$id % count($colors)];
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
        return redirect()->route('login');
    }
}; ?>

<div>
    @include('layouts.partials.student-sidebar')
    <main class="main-content">
        <div class="topbar">
            <div>
                <h2 class="text-dark">Candidate <span class="text-primary">Profiles & Platforms</span></h2>
                <p class="text-secondary mb-0">Learn about candidate advocacy before casting your vote</p>
            </div>

            @if ($this->isEligibleToEdit)
                @if ($lockChanges)
                    <button class="btn btn-glow btn-sm shadow-sm" disabled>
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="bi bi-lock-fill"></i>
                            <span class="ms-2 d-none d-sm-inline">Edit Platform is Locked</span>
                        </div>
                    </button>
                @else
                    <button class="btn btn-glow btn-sm shadow-sm" data-bs-toggle="modal"
                        data-bs-target="#editMyPlatformModal">
                        <div class="d-flex align-items-center justify-content-center">
                            <i class="bi bi-pencil-square"></i>
                            <span class="ms-2 d-none d-sm-inline">Edit My Platform</span>
                        </div>
                    </button>
                @endif
            @endif
        </div>

        @if ($this->showProfiles)
            <div class="d-flex gap-1 gap-md-2 flex-wrap mb-3">
                @foreach ($this->positionsList as $pos)
                    <button wire:click="selectPosition('{{ $pos }}')"
                        class="tab-custom {{ $selectedPosition === $pos ? 'active' : '' }}"
                        style="font-size: 0.75rem; padding: 4px 8px;">
                        {{ $pos }}
                    </button>
                @endforeach
            </div>

            <div class="row g-2 g-md-4">
                @forelse($this->filteredCandidates as $candidate)
                    <div class="col-6 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-4"
                        wire:key="candidate-card-{{ $candidate->id }}">
                        @php
                            $latestPlatform = $candidate->platforms->first();
                            $isApproved = $latestPlatform && $latestPlatform->status === 'approved';
                        @endphp

                        <div
                            class="position-relative bg-white rounded-5 shadow-sm border transition-all hover-translate-y hover-shadow-lg p-2 p-md-4 h-100 d-flex flex-column align-items-center text-center group-card {{ !$isApproved ? 'opacity-75' : '' }}">

                            <!-- Badge and Info Icon -->
                            <div
                                class="position-absolute top-0 start-0 w-100 p-2 p-md-3 d-flex justify-content-between align-items-center">
                                <span
                                    class="badge rounded-pill {{ $isApproved ? 'bg-emerald-light text-primary' : 'bg-light text-muted' }} fw-bold px-2 px-md-3 py-1"
                                    style="font-size: 0.55rem; max-width: 80%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ Str::limit($candidate->party_name ?? 'No Party Name', 15) }}
                                </span>

                                @if ($isApproved)
                                    <button type="button" class="btn p-0 border-0" data-bs-toggle="modal"
                                        data-bs-target="#unifiedModal{{ $candidate->id }}">
                                        <i class="bi bi-info-circle-fill text-primary opacity-50"
                                            style="font-size: 0.8rem;"></i>
                                    </button>
                                @else
                                    <i class="bi bi-hourglass-split text-muted" style="font-size: 0.8rem;"></i>
                                @endif
                            </div>

                            <!-- Candidate Avatar -->
                            <div class="mt-4 mt-md-4 mb-2 mb-md-3 position-relative">
                                <div class="candidate-avatar-circle shadow-md border border-4 border-white overflow-hidden mx-auto"
                                    style="width: clamp(60px, 15vw, 120px); height: clamp(60px, 15vw, 120px); background: {{ $this->getAvatarColor($candidate->id) }}; filter: {{ !$isApproved ? 'grayscale(100%)' : 'none' }};">
                                    @if ($candidate->photo)
                                        <img src="{{ asset('storage/' . $candidate->photo) }}"
                                            class="w-100 h-100 object-fit-cover">
                                    @elseif ($candidate->student?->photo)
                                        <img src="{{ asset('storage/' . $candidate->student->photo) }}"
                                            class="w-100 h-100 object-fit-cover">
                                    @else
                                        <div
                                            class="w-100 h-100 d-flex align-items-center justify-content-center text-white fw-bold fs-4 fs-md-3">
                                            {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Candidate Info -->
                            <div class="mb-1 mb-md-2 w-100">
                                <h6 class="fw-black text-dark mb-0 text-truncate px-1"
                                    style="font-size: clamp(0.7rem, 2vw, 0.95rem);">
                                    {{ $candidate->student?->first_name }} {{ $candidate->student?->last_name }}
                                </h6>
                                <p class="text-uppercase tracking-wider text-primary fw-bold mb-0"
                                    style="font-size: 0.55rem;">
                                    {{ $candidate->position?->name }}
                                </p>
                            </div>

                            <!-- Tagline Box -->
                            <div class="bg-light rounded-4 p-2 p-md-3 mb-2 mb-md-3 w-100">
                                <p class="text-secondary mb-0 fst-italic"
                                    style="font-size: 0.6rem; line-height: 1.3; min-height: 30px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    @if ($isApproved)
                                        "{{ $latestPlatform->tagline }}"
                                    @else
                                        <span class="text-muted"><i class="bi bi-lock-fill me-1"></i> Hidden</span>
                                    @endif
                                </p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-auto w-100 d-flex flex-column gap-1 gap-md-2">
                                @if ($isApproved)
                                    <button class="btn btn-outline-glow rounded-pill py-1 py-md-2 fw-bold w-100"
                                        style="font-size: 0.65rem;" data-bs-toggle="modal"
                                        data-bs-target="#unifiedModal{{ $candidate->id }}"
                                        onclick="const tab = bootstrap.Tab.getOrCreateInstance(document.querySelector('#profile-tab-{{ $candidate->id }}')); tab.show();">
                                        Profile
                                    </button>
                                @else
                                    <button
                                        class="btn btn-light rounded-pill py-1 py-md-2 fw-bold w-100 text-muted border border-dashed"
                                        style="font-size: 0.65rem;" disabled>
                                        Review
                                    </button>
                                @endif

                                @if ($isVotingOpen && $isApproved)
                                    <a href="{{ url('/students/cast-vote') }}" wire:navigate
                                        class="btn btn-glow rounded-pill py-1 py-md-2 fw-bold shadow-sm w-100 text-decoration-none"
                                        style="font-size: 0.65rem;">
                                        Vote Now
                                    </a>
                                @else
                                    <button class="btn btn-secondary rounded-pill py-1 py-md-2 opacity-50 w-100"
                                        style="font-size: 0.65rem;" disabled>
                                        <i class="bi bi-lock-fill"></i> Locked
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 py-5 text-center">
                        <div class="bg-light rounded-5 p-5">
                            <i class="bi bi-person-x fs-1 text-muted"></i>
                            <p class="text-muted mt-3">No candidates found.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        @else
            <div class="glass-card p-5 text-center my-5 shadow-sm border-0 rounded-4">
                <div class="mb-4">
                    <i class="bi bi-eye-slash text-muted" style="font-size: 4rem;"></i>
                </div>
                <h3 class="fw-bold text-dark">Profiles are currently hidden</h3>
                <p class="text-secondary mx-auto" style="max-width: 500px;">
                    The administrator hasn't released the official candidate profiles yet.
                </p>
            </div>
        @endif
    </main>

    @if ($this->showProfiles)
        @foreach ($this->filteredCandidates as $candidate)
            @php $approvedPlatform = $candidate->platforms->first(); @endphp

            <div class="modal fade" id="unifiedModal{{ $candidate->id }}" tabindex="-1" wire:ignore.self>
                <div class="modal-dialog modal-dialog-centered modal-lg mx-3 mx-md-auto">
                    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">

                        <!-- Modal Header with Navigation Tabs -->
                        <div class="modal-header border-0 bg-emerald-light p-3 pb-0 flex-column align-items-start">
                            <div class="d-flex justify-content-between w-100">
                                <h6 class="fw-black text-primary mb-0">Candidate Details</h6>
                                <button type="button" class="btn-close" style="font-size: 0.7rem;"
                                    data-bs-dismiss="modal"></button>
                            </div>

                            <ul class="nav nav-tabs border-0 mt-2 w-100" role="tablist">
                                <li class="nav-item flex-fill text-center">
                                    <button class="nav-link active fw-bold border-0 bg-transparent w-100 py-2 small"
                                        id="profile-tab-{{ $candidate->id }}" data-bs-toggle="tab"
                                        data-bs-target="#profile-panel-{{ $candidate->id }}" type="button"
                                        style="font-size: 0.75rem;">
                                        Introductory Profile
                                    </button>
                                </li>
                                <li class="nav-item flex-fill text-center">
                                    <button class="nav-link fw-bold border-0 bg-transparent w-100 py-2 small"
                                        id="platform-tab-{{ $candidate->id }}" data-bs-toggle="tab"
                                        data-bs-target="#platform-panel-{{ $candidate->id }}" type="button"
                                        style="font-size: 0.75rem;">
                                        Platform
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="modal-body p-3">
                            <div class="tab-content">

                                <!-- Introductory Profile Panel -->
                                <div class="tab-pane fade show active" id="profile-panel-{{ $candidate->id }}">
                                    <div class="row g-3">
                                        <!-- Left Column: Avatar & Basic Info -->
                                        <div class="col-12 col-md-4 text-center border-md-end">
                                            <p class="text-primary fw-bold text-uppercase mb-2"
                                                style="font-size: 0.80rem; letter-spacing: 1px;">
                                                {{ $candidate->party_name }}
                                            </p>

                                            <div class="rounded-circle mx-auto mb-2 shadow-sm border border-2 border-white"
                                                style="width:80px; height:80px; overflow:hidden; background: {{ $this->getAvatarColor($candidate->id) }}">
                                                @if ($candidate->photo)
                                                    <img src="{{ asset('storage/' . $candidate->photo) }}"
                                                        class="w-100 h-100 object-fit-cover">
                                                @elseif ($candidate->student?->photo)
                                                    <img src="{{ asset('storage/' . $candidate->student->photo) }}"
                                                        class="w-100 h-100 object-fit-cover">
                                                @else
                                                    <div
                                                        class="w-100 h-100 d-flex align-items-center justify-content-center text-white fw-bold fs-4 fs-md-3">
                                                        {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>

                                            <h6 class="fw-black text-dark mb-0" style="font-size: 0.9rem;">
                                                {{ $candidate->student?->first_name }}
                                                {{ $candidate->student?->middle_name ? substr($candidate->student->middle_name, 0, 1) . '.' : '' }}
                                                {{ $candidate->student?->last_name }}
                                                {{ $candidate->student?->suffix }}
                                            </h6>

                                            <p class="text-primary fw-bold text-uppercase mb-2"
                                                style="font-size: 0.90rem; letter-spacing: 1px;">
                                                {{ $candidate->student?->course }} -
                                                {{ $candidate->student?->year_level }}
                                            </p>
                                        </div>

                                        <!-- Right Column: Achievements & Stats -->
                                        <div class="col-12 col-md-8 px-md-3">
                                            <h6 class="text-uppercase small fw-black text-primary mb-2"
                                                style="font-size: 0.80rem;">Achievements</h6>
                                            <div class="mb-3" style="max-height: 120px; overflow-y: auto;">
                                                <div
                                                    class="p-2 rounded-3 bg-light mb-1 border-0 d-flex align-items-center">
                                                    <i class="bi bi-dot text-primary fs-4"></i>
                                                    {{ is_array($candidate->achievements)
                                                        ? (implode(', ', $candidate->achievements) ?:
                                                            'No achievement listed')
                                                        : $candidate->achievements ?? 'No achievement listed' }}
                                                </div>
                                            </div>

                                            <div class="row g-0 border-top pt-2">
                                                <div class="col-6 text-center border-end">
                                                    <span class="text-primary fw-bold d-block"
                                                        style="font-size: 0.80rem;">GWA</span>
                                                    <span
                                                        class="text-dark small">{{ $candidate->average_grade ?: 'N/A' }}</span>
                                                </div>
                                                <div class="col-6 text-center">
                                                    <span class="text-primary fw-bold d-block"
                                                        style="font-size: 0.80rem;">Previous Project</span>
                                                    <span class="text-black small">
                                                        {{ is_array($candidate->previous_school_project)
                                                            ? (implode(', ', $candidate->previous_school_project) ?:
                                                                'No project listed')
                                                            : $candidate->previous_school_project ?? 'No project listed' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Platform Panel -->
                                <div class="tab-pane fade" id="platform-panel-{{ $candidate->id }}">
                                    <div class="bg-light p-2 rounded-3 mb-2">
                                        <h6 class="fw-black text-dark mb-1" style="font-size: 0.80rem;">
                                            {{ $approvedPlatform->title ?? 'Platform' }}
                                        </h6>
                                        <p class="text-primary small fst-italic mb-0" style="font-size: 0.65rem;">
                                            "{{ $approvedPlatform->tagline }}"
                                        </p>
                                    </div>

                                    <div class="agenda-list" style="max-height: 150px; overflow-y: auto;">
                                        @php
                                            $agenda = !empty($approvedPlatform->agenda)
                                                ? (is_array($approvedPlatform->agenda)
                                                    ? $approvedPlatform->agenda
                                                    : explode("\n", $approvedPlatform->agenda))
                                                : [];
                                        @endphp

                                        @foreach (array_filter($agenda) as $point)
                                            <div class="d-flex align-items-center mb-1 p-1">
                                                <i class="bi bi-check2 text-primary me-2"></i>
                                                <span class="small text-dark" style="font-size: 0.80rem;">
                                                    {{ trim($point) }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

    @if ($this->isEligibleToEdit)
        <div class="modal fade" id="editMyPlatformModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable mx-3 mx-md-auto">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">

                    <!-- Modal Header -->
                    <div class="modal-header bg-emerald-light border-0 pb-0 pt-3 px-3 px-md-4">
                        <div class="w-100">
                            <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
                                <h6 class="modal-title fw-bold text-primary mb-0">Edit Campaign Information</h6>
                                <button type="button" class="btn-close" style="font-size: 0.8rem;"
                                    data-bs-dismiss="modal"></button>
                            </div>

                            <ul class="nav nav-tabs border-0 nav-justified w-100" id="editTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button
                                        class="nav-link active border-0 rounded-0 py-2 fw-bold text-secondary w-100 small"
                                        id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile-pane"
                                        type="button" role="tab" style="background: transparent;">
                                        <i class="bi bi-person-circle me-1"></i>Profile
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link border-0 rounded-0 py-2 fw-bold text-secondary w-100 small"
                                        id="platform-tab" data-bs-toggle="tab" data-bs-target="#platform-pane"
                                        type="button" role="tab" style="background: transparent;">
                                        <i class="bi bi-megaphone me-1"></i>Platform
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <form wire:submit.prevent="updatePlatform">
                        <div class="modal-body p-3 p-md-4 bg-white">
                            @if ($lockChanges)
                                <div class="alert alert-warning border-0 rounded-3 small mb-3 py-2 px-3"
                                    style="font-size: 0.75rem;">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    <strong>Notice:</strong> Editing is disabled. Profile updates are currently frozen.
                                </div>
                            @endif

                            <div class="tab-content" id="editTabsContent">
                                <!-- Profile Pane -->
                                <div class="tab-pane fade show active" id="profile-pane" role="tabpanel">
                                    <div class="row g-3">
                                        <!-- Photo Upload -->
                                        <div class="col-12 col-md-4 mb-2 text-center border-md-end">
                                            <label class="small fw-bold text-muted d-block mb-2">Candidate
                                                Photo</label>
                                            <div class="text-center">
                                                <input type="file" id="candidatePhotoInput"
                                                    wire:model="candidate_photo" class="d-none" accept="image/*">
                                                <label for="candidatePhotoInput"
                                                    class="mx-auto shadow-sm d-flex align-items-center justify-content-center position-relative profile-upload-circle"
                                                    style="width: 100px; height: 100px; border-radius: 50%; overflow: hidden; cursor: pointer; border: 4px solid white; background: #f8fafc;">

                                                    @if ($candidate_photo)
                                                        <img src="{{ $candidate_photo->temporaryUrl() }}"
                                                            class="w-100 h-100 object-fit-cover">
                                                    @elseif ($existing_candidate_photo)
                                                        <img src="{{ asset('storage/' . $existing_candidate_photo) }}"
                                                            class="w-100 h-100 object-fit-cover">
                                                    @else
                                                        <i class="bi bi-camera-fill fs-2 text-muted"></i>
                                                    @endif

                                                    <div class="upload-overlay">
                                                        <i class="bi bi-plus-lg text-white fs-5"></i>
                                                    </div>
                                                </label>
                                                <small class="text-muted d-block mt-2 fw-bold text-uppercase"
                                                    style="font-size: 9px;">Tap to change</small>
                                                @error('candidate_photo')
                                                    <span class="text-danger d-block mt-1"
                                                        style="font-size: 0.65rem;">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <!-- Basic Info -->
                                        <div class="col-12 col-md-8">
                                            <div class="row g-2">
                                                <div class="col-6 mb-2">
                                                    <label class="small fw-bold text-muted"
                                                        style="font-size: 0.7rem;">Party Name</label>
                                                    <input type="text" wire:model="party_name"
                                                        class="form-control form-control-sm border-0 bg-light py-2"
                                                        placeholder="e.g. Independent">
                                                </div>
                                                <div class="col-6 mb-2">
                                                    <label class="small fw-bold text-muted"
                                                        style="font-size: 0.7rem;">GWA (Optional)</label>
                                                    <input type="text" wire:model="average_grade"
                                                        class="form-control form-control-sm border-0 bg-light py-2"
                                                        placeholder="e.g. 1.0">
                                                </div>
                                                <div class="col-12">
                                                    <label class="small fw-bold text-muted"
                                                        style="font-size: 0.7rem;">Achievements (Press Enter for new
                                                        line)</label>
                                                    <textarea wire:model="achievements" rows="2" class="form-control form-control-sm border-0 bg-light"
                                                        placeholder="Key achievements..."></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="my-3 opacity-25">

                                        <!-- Dynamic Fields: Previous Positions -->
                                        <div class="col-12 col-md-6">
                                            <label class="small fw-bold text-primary mb-1 d-block"
                                                style="font-size: 0.7rem;">Previous Positions</label>
                                            @foreach ($previous_position as $index => $pos)
                                                <div class="d-flex gap-1 mb-2" wire:key="pos-{{ $index }}">
                                                    <input type="text"
                                                        wire:model="previous_position.{{ $index }}"
                                                        class="form-control form-control-sm border-0 bg-light py-1"
                                                        placeholder="Position Name">
                                                    <button type="button"
                                                        wire:click="removeField('previous_position', {{ $index }})"
                                                        class="btn btn-sm text-danger p-0">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </div>
                                            @endforeach
                                            <button type="button" wire:click="addField('previous_position')"
                                                class="btn btn-sm btn-link text-primary p-0 text-decoration-none"
                                                style="font-size: 0.7rem;">
                                                + Add Position
                                            </button>
                                        </div>

                                        <!-- Dynamic Fields: Previous Projects -->
                                        <div class="col-12 col-md-6">
                                            <label class="small fw-bold text-primary mb-1 d-block"
                                                style="font-size: 0.7rem;">Previous School Projects</label>
                                            @foreach ($previous_school_project as $index => $proj)
                                                <div class="d-flex gap-1 mb-2" wire:key="proj-{{ $index }}">
                                                    <input type="text"
                                                        wire:model="previous_school_project.{{ $index }}"
                                                        class="form-control form-control-sm border-0 bg-light py-1"
                                                        placeholder="Project Name">
                                                    <button type="button"
                                                        wire:click="removeField('previous_school_project', {{ $index }})"
                                                        class="btn btn-sm text-danger p-0">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </div>
                                            @endforeach
                                            <button type="button" wire:click="addField('previous_school_project')"
                                                class="btn btn-sm btn-link text-primary p-0 text-decoration-none"
                                                style="font-size: 0.7rem;">
                                                + Add Project
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Platform Pane -->
                                <div class="tab-pane fade" id="platform-pane" role="tabpanel">
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted" style="font-size: 0.7rem;">Campaign
                                            Tagline</label>
                                        <input type="text" wire:model="tagline"
                                            class="form-control form-control-sm border-0 bg-light py-2"
                                            placeholder="e.g. Service with Integrity">
                                        @error('tagline')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted" style="font-size: 0.7rem;">Platform
                                            Title</label>
                                        <input type="text" wire:model="platform_title"
                                            class="form-control form-control-sm border-0 bg-light py-2"
                                            placeholder="Main Goal/Title">
                                        @error('platform_title')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="mb-2">
                                        <label class="small fw-bold text-muted" style="font-size: 0.7rem;">Agenda
                                            Details (List your plans)</label>
                                        <textarea wire:model="agenda" rows="5" class="form-control form-control-sm border-0 bg-light"
                                            placeholder="1. Agenda Item One&#10;2. Agenda Item Two..."></textarea>
                                        @error('agenda')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="modal-footer border-0 bg-light p-2 p-md-3">
                            @if ($lockChanges)
                                <button type="button" class="btn btn-secondary btn-sm px-4 rounded-3" disabled
                                    style="font-size: 0.75rem;">
                                    <i class="bi bi-lock-fill me-1"></i> Changes Locked
                                </button>
                            @else
                                <button type="submit" class="btn btn-glow px-4 rounded-pill fw-bold"
                                    wire:loading.attr="disabled">
                                    <span wire:loading.remove>Submit for Review</span>
                                    <span wire:loading>
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                        Saving...
                                    </span>
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

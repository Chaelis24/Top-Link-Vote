<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\{Layout, Title, Computed, Url};
use App\Traits\{ChecksMaintenance, AuthenticatesLogout};
use Illuminate\Support\Facades\{Auth, Storage, Log};
use App\Services\Student\ProfilePlatformService;
use App\Models\{ElectionCycle, Platform, Student, Candidate, Block, Course};

new #[Layout('layouts.app')] #[Title('Platforms')] class extends Component {
    use ChecksMaintenance, AuthenticatesLogout, WithFileUploads;

    #[Url]
    public string $selectedPosition = 'All Positions';
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

    private ProfilePlatformService $profilePlatformService;

    public function boot(ProfilePlatformService $profilePlatformService)
    {
        $this->profilePlatformService = $profilePlatformService;
    }

    public function mount()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $studentData = $this->profilePlatformService->getStudentData($user);
        $this->student = $studentData['student'];
        $this->profile_photo_path = $studentData['profile_photo_path'];

        if ($this->isEligibleToEdit) {
            $candidateData = $this->profilePlatformService->loadCandidateData($user);
            $this->achievements = $candidateData['achievements'];
            $this->party_name = $candidateData['party_name'];
            $this->previous_position = $candidateData['previous_position'];
            $this->previous_school_project = $candidateData['previous_school_project'];
            $this->average_grade = $candidateData['average_grade'];
            $this->existing_candidate_photo = $candidateData['existing_candidate_photo'];
            $this->platform_title = $candidateData['platform_title'];
            $this->tagline = $candidateData['tagline'];
            $this->agenda = $candidateData['agenda'];
        }
    }

    #[Computed]
    public function activeCycle()
    {
        return $this->profilePlatformService->getActiveCycle();
    }

    #[Computed]
    public function isEligibleToEdit()
    {
        return $this->profilePlatformService->isEligibleToEdit();
    }

    #[Computed]
    public function isFilingOpen()
    {
        return $this->profilePlatformService->isFilingOpen($this->activeCycle);
    }

    #[Computed]
    public function isCampaignOpen()
    {
        return $this->profilePlatformService->isCampaignOpen($this->activeCycle);
    }

    #[Computed]
    public function isVotingOpen()
    {
        return $this->profilePlatformService->isVotingOpen($this->activeCycle);
    }

    #[Computed]
    public function positionsList()
    {
        $courseId = $this->profilePlatformService->getStudentCourseId($this->student);
        return $this->profilePlatformService->getPositionsList($this->activeCycle, $courseId);
    }

    #[Computed]
    public function filteredCandidates()
    {
        $courseId = $this->profilePlatformService->getStudentCourseId($this->student);
        return $this->profilePlatformService->getFilteredCandidates($this->activeCycle, $courseId, $this->selectedPosition);
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

    public function selectPosition($pos)
    {
        $this->selectedPosition = $pos;
    }

    public function updatePlatform()
    {
        if (!Auth::user()->hasRole('candidate')) {
            abort(403);
        }

        $active = $this->activeCycle;
        $isVotingStarted = $active && now()->gt($active->voting_start);

        if (!$this->isEligibleToEdit || $this->lockChanges || $isVotingStarted) {
            $this->dispatch('swal', [
                'title' => 'Changes Locked',
                'text' => $isVotingStarted ? 'Voting is ongoing. Profiles are now frozen.' : 'Changes are currently locked.',
                'icon' => 'warning',
            ]);
            return;
        }

        $request = new \App\Http\Requests\Students\UpdateCandidateRequest();
        $validated = $this->validate(
            array_merge($request->rules(), [
                'candidate_photo' => 'nullable|image|max:2048',
            ]),
        );

        try {
            $candidate = Auth::user()->candidate;

            if ($this->candidate_photo) {
                if ($candidate->photo && Storage::disk('public')->exists($candidate->photo)) {
                    Storage::disk('public')->delete($candidate->photo);
                }
                $filename = 'candidate_' . $candidate->id . '_' . time() . '.' . $this->candidate_photo->getClientOriginalExtension();
                $photoPath = $this->candidate_photo->storeAs('candidates-picture', $filename, 'public');
            } else {
                $photoPath = $candidate->photo;
            }

            $grade = $validated['average_grade'] === '' || $validated['average_grade'] === null ? null : $validated['average_grade'];

            $candidate->update([
                'party_name' => $validated['party_name'],
                'achievements' => implode("\n", (array) $this->achievements),
                'average_grade' => $grade,
                'previous_position' => $validated['previous_position'],
                'previous_school_project' => $validated['previous_school_project'],
                'photo' => $photoPath,
            ]);

            \App\Models\Platform::updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'title' => $validated['platform_title'],
                    'tagline' => $validated['tagline'],
                    'agenda' => explode("\n", $validated['agenda']),
                    'status' => 'pending',
                    'submitted_at' => now(),
                ],
            );

            \App\Jobs\LogActivity::dispatch([
                'user_id' => Auth::id(),
                'student_id' => Auth::user()->student->id,
                'action' => 'Update Platform',
                'description' => 'Candidate updated their profile and platform.',
                'properties' => [
                    'candidate_id' => $candidate->id,
                    'party' => $validated['party_name'],
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])->onQueue('logs');

            $this->dispatch('swal', [
                'title' => 'Submission Successful!',
                'text' => 'Your profile and platform have been submitted for review.',
                'icon' => 'success',
                'timer' => 3000,
                'showConfirmButton' => false,
            ]);
            $this->dispatch('close-modal');
        } catch (\Exception $e) {
            Log::error('Update Error: ' . $e->getMessage());
            $this->dispatch('swal', [
                'title' => 'Error',
                'text' => 'Something went wrong while submitting.' . $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    public function getAvatarColor($id)
    {
        return $this->profilePlatformService->getAvatarColor($id);
    }
}; ?>

<div>
    <div
        class="d-lg-none d-flex align-items-center justify-content-start p-2 px-4 bg-white shadow-sm gap-2 border-bottom">
        <img src="{{ asset('images/logo.png') }}" alt="Logo" style="height: 45px; width: 45px; object-fit: contain;">

        <h4 class="mb-0 text-primary" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
            Top Link Global College, Inc.
        </h4>
    </div>

    @include('layouts.partials.student-sidebar')
    <main class="main-content">
        <div class="topbar">
            <div>
                <h2 class="text-dark">Candidate <span class="text-primary">Profiles & Platforms</span></h2>
                <p class="text-secondary mb-0 small">Learn about candidate advocacy before casting your vote</p>
            </div>

            @if ($this->isEligibleToEdit)
                @php
                    $active = $this->activeCycle;
                    $now = now();

                    $isVotingStarted = $active && $now->gt($active->voting_start);
                    $isVotingFinished = $active && $now->gt($active->voting_end);

                    $isLocked = $isVotingStarted || $isVotingFinished;
                @endphp
                @if ($isLocked)
                    <button class="btn btn-glow btn-sm shadow-sm" style="cursor: not-allowed; opacity: 0.8;" disabled>
                        <div class="d-flex align-items-center justify-content-center text-danger">
                            <i class="bi {{ $isVotingFinished ? 'bi-archive-fill' : 'bi-lock-fill' }}"></i>

                            <span class="ms-2 d-none d-sm-inline">
                                @if ($isVotingFinished)
                                    Voting Closed: Edits Disabled
                                @elseif ($isVotingStarted)
                                    Voting Active: Changes Locked
                                @else
                                    Campaign Ended: Edits Locked
                                @endif
                            </span>
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

        @php
            $user = Auth::user();
            $isStudentOnly = $user->hasRole('student') && !$user->hasRole('candidate');
            $isFiling = $this->isFilingOpen;
            $isCampaign = $this->isCampaignOpen;
            $isVoting = $this->isVotingOpen;

            $canAccess = $isVoting || $isCampaign || ($isFiling && !$isStudentOnly);
        @endphp

        @if ($canAccess)
            <div class="d-flex gap-1 gap-md-2 flex-wrap mb-3">
                @foreach ($this->positionsList as $pos)
                    <button wire:click="selectPosition('{{ $pos }}')"
                        @click="selectedPosition = '{{ $pos }}'"
                        class="tab-custom {{ $selectedPosition === $pos ? 'active' : '' }} text-xs md:text-base px-2 py-2 md:px-3 md:py-3"
                        style="font-size: 0.8rem;">
                        {{ $pos }}
                    </button>
                @endforeach
            </div>

            <div class="row g-2 g-md-4 mb-12 mb-md-0">
                @forelse($this->filteredCandidates as $candidate)
                    <div class="col-6 col-md-6 col-lg-4 col-xl-3 mb-2 mb-md-3"
                        wire:key="candidate-box-{{ $selectedPosition }}-{{ $candidate->id }}">
                        @php
                            $latestPlatform = $candidate->platforms->first();
                            $isApproved = $latestPlatform && $latestPlatform->status === 'approved';

                            $suffix = $candidate->student->suffix;
                            $formattedSuffix = in_array($suffix, ['Jr', 'Sr']) ? $suffix . '.' : $suffix;
                        @endphp

                        <div
                            class="position-relative bg-white rounded-5 shadow-sm border transition-all hover-translate-y hover-shadow-lg p-2 p-md-3 h-100 d-flex flex-column align-items-center text-center group-card mb-0 mb-md-0 {{ !$isApproved ? 'opacity-75' : '' }}">

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

                            <div class="mt-4 mt-md-4 mb-2 mb-md-3 position-relative">
                                <div class="candidate-avatar-circle shadow-md border border-4 border-white overflow-hidden mx-auto"
                                    style="width: clamp(60px, 15vw, 120px); height: clamp(60px, 15vw, 120px); background: {{ $this->getAvatarColor($candidate->id) }}; filter: {{ !$isApproved ? 'grayscale(100%)' : 'none' }};">
                                    @if ($candidate->photo)
                                        <img src="{{ asset('storage/' . $candidate->photo) }}"
                                            class="w-100 h-100 object-fit-cover">
                                    @else
                                        <div
                                            class="w-100 h-100 d-flex align-items-center justify-content-center text-white fw-bold fs-4 fs-md-3">
                                            {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-1 mb-md-2 w-100">
                                <h6 class="fw-black text-dark mb-0 text-truncate px-1"
                                    style="font-size: clamp(0.7rem, 2vw, 0.95rem);">
                                    {{ $candidate->student?->first_name }}
                                    {{ $candidate->student?->middle_name ? substr($candidate->student?->middle_name, 0, 1) . '.' : '' }}
                                    {{ $candidate->student?->last_name }} {{ $formattedSuffix ?? '' }}
                                </h6>
                                <p class="text-uppercase tracking-wider text-primary fw-bold mb-0"
                                    style="font-size: 0.60rem;">
                                    {{ $candidate->position?->name }}
                                </p>
                            </div>

                            <div class="bg-light rounded-4 p-2 p-md-3 mb-2 mb-md-3 w-100">
                                <p class="text-primary mb-0 fst-italic"
                                    style="font-size: 0.70rem; line-height: 1.3; min-height: 3px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    @if ($isApproved)
                                        "{{ $latestPlatform?->tagline ?? 'No Tagline' }}"
                                    @else
                                        <span class="text-muted"><i class="bi bi-lock-fill me-1"></i> Hidden</span>
                                    @endif
                                </p>
                            </div>

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
            @php
                $latestCycle = ElectionCycle::where('status', 'active')->first();
            @endphp

            @if ($latestCycle && $latestCycle->status === 'completed')
                <div class="p-5 text-center my-5 rounded-4">
                    <div class="mb-4">
                        <div class="position-relative d-inline-block">
                            <i class="bi bi-calendar-check text-success opacity-75" style="font-size: 5rem;"></i>
                            <i class="bi bi-check-circle-fill text-success position-absolute bottom-0 end-0"
                                style="font-size: 2rem;"></i>
                        </div>
                    </div>
                    <h2 class="fw-black text-dark">Voting Period Finished</h2>
                    <p class="text-secondary mx-auto mb-4" style="max-width: 500px;">
                        Voting officially closed on
                        <strong class="text-primary">
                            {{ $latestCycle->voting_end?->format('M d, Y') ?? 'the scheduled deadline' }}
                        </strong>.
                        Candidate profiles and ballot submissions are now locked while results are being finalized.
                    </p>
                </div>
            @elseif ($isFiling && $isStudentOnly)
                <div class="p-5 text-center my-5 rounded-4">
                    <div class="p-5 text-center my-5 rounded-4">
                        <h3 class="text-primary fw-bold">Filing of Candidacy Ongoing</h3>
                        <p class="text-secondary">The candidates are currently finalizing their platforms. Campaigning
                            will start soon!</p>
                    </div>
                </div>
            @elseif ($isCampaign && $isStudentOnly)
                <div class="p-5 text-center my-5 rounded-4">
                    <h3 class="text-primary fw-bold">Campaign of Candidacy Ongoing</h3>
                    <p class="text-secondary">The candidates are currently campaigning. Voting will start soon!</p>
                </div>
            @else
                <div class="p-5 text-center">
                    <div class="mb-6 relative inline-block">
                        <div class="bg-emerald-50 p-6 rounded-full">
                            <i class="bi bi-box-seam text-emerald-600/30 text-6xl"></i>
                        </div>
                        <div class="absolute -bottom-2 -right-2 bg-white rounded-full p-1">
                            <i class="bi bi-exclamation-circle-fill text-amber-500 text-3xl"></i>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800 mb-3">No Active Election</h2>
                    <p class="text-secondary mx-auto mb-4" style="max-width: 500px;">
                        There is currently no ongoing election cycle or the voting period has not been opened yet by the
                        administrator.
                        Please check back later for official announcements.
                    </p>
                </div>
            @endif
        @endif
    </main>

    @foreach ($this->filteredCandidates as $candidate)
        @php
            $approvedPlatform = $candidate->platforms->first();

            $suffix = $candidate->student->suffix;
            $formattedSuffix = in_array($suffix, ['Jr', 'Sr']) ? $suffix . '.' : $suffix;
        @endphp

        <div class="modal fade" id="unifiedModal{{ $candidate->id }}" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg mx-3 mx-md-auto">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden min-h-[50vh] md:h-[10px]">

                    <div class="modal-header border-0 p-3 pb-2 flex-column align-items-start bg-emerald-50">
                        <div class="d-flex justify-content-between align-items-center w-100 mb-2">
                            <span class="badge bg-white fw-bold px-2 py-1 shadow-sm text-emerald-600"
                                style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                CANDIDATE PROFILE
                            </span>
                            <button type="button" class="btn-close" style="font-size: 0.7rem;"
                                data-bs-dismiss="modal"></button>
                        </div>

                        <ul class="nav nav-pills w-100 p-1 rounded-3 bg-transparent" role="tablist">
                            <li class="nav-item flex-fill text-center" role="presentation">
                                <button
                                    class="nav-link active fw-bold border-0 w-100 py-2 small rounded-3 !text-emerald-700 bg-transparent [&.active]:!bg-emerald-600 [&.active]:!text-white"
                                    id="profile-tab-{{ $candidate->id }}" data-bs-toggle="tab"
                                    data-bs-target="#profile-panel-{{ $candidate->id }}" type="button"
                                    style="font-size: 0.75rem; transition: all 0.2s ease;">
                                    <i class="bi bi-person-badge me-1"></i> Introductory Profile
                                </button>
                            </li>
                            <li class="nav-item flex-fill text-center" role="presentation">
                                <button
                                    class="nav-link fw-bold border-0 w-100 py-2 small rounded-3 !text-emerald-700 bg-transparent [&.active]:!bg-emerald-600 [&.active]:!text-white"
                                    id="platform-tab-{{ $candidate->id }}" data-bs-toggle="tab"
                                    data-bs-target="#platform-panel-{{ $candidate->id }}" type="button"
                                    style="font-size: 0.75rem; transition: all 0.2s ease;">
                                    <i class="bi bi-megaphone me-1"></i> Platform & Agenda
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="modal-body p-3 pt-4">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="profile-panel-{{ $candidate->id }}"
                                role="tabpanel">
                                <div class="row g-4 align-items-center">

                                    <div class="col-12 col-md-4 text-center border-md-end pe-md-4">
                                        <div class="position-relative d-inline-block mb-3">
                                            <div class="rounded-circle mx-auto shadow border border-3 border-white"
                                                style="width:70px; height:70px; overflow:hidden; background: {{ $this->getAvatarColor($candidate->id) }}">
                                                @if ($candidate->photo)
                                                    <img src="{{ asset('storage/' . $candidate->photo) }}"
                                                        class="w-100 h-100 object-fit-cover">
                                                @else
                                                    <div
                                                        class="w-100 h-100 d-flex align-items-center justify-content-center text-white fw-black fs-3">
                                                        {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>

                                        <h5 class="fw-black text-primary mb-1"
                                            style="font-size: 1rem; letter-spacing: 0.5px;">
                                            {{ $candidate->student?->first_name }}
                                            {{ $candidate->student?->middle_name ? substr($candidate->student->middle_name, 0, 1) . '.' : '' }}
                                            {{ $candidate->student?->last_name }}
                                            {{ $formattedSuffix ?? '' }}
                                        </h5>

                                        <p class="text-muted fw-semibold small mb-2" style="font-size: 0.75rem;">
                                            {{ $candidate->student?->block?->course?->name ?? 'N/A' }}
                                            -
                                            {{ $candidate->student?->block?->year_level }}{{ $candidate->student?->block?->section }}
                                        </p>

                                        <span
                                            class="badge bg-white rounded-pill px-3 py-1 fw-bold text-uppercase text-emerald-600 border border-emerald-200"
                                            style="font-size: 0.70rem; letter-spacing: 0.5px;">
                                            {{ $candidate->party_name ?? 'Independent' }}
                                        </span>
                                    </div>

                                    <div class="col-12 col-md-8 ps-md-4">
                                        <div class="mb-3">
                                            <h6 class="text-uppercase small fw-semibold mb-2 d-flex align-items-center text-emerald-600"
                                                style="font-size: 0.75rem; letter-spacing: 0.5px;">
                                                <i class="bi bi-trophy me-2"></i> Achievements
                                            </h6>
                                            <div style="max-height: 110px; overflow-y: auto; padding-right: 4px;">
                                                <div class="p-2.5 rounded-3 bg-white border border-emerald-600 shadow-sm"
                                                    style="font-size: 0.82rem; line-height: 1.5;">
                                                    {{ is_array($candidate->achievements)
                                                        ? (implode(', ', $candidate->achievements) ?:
                                                            'No achievements listed.')
                                                        : $candidate->achievements ?? 'No achievements listed.' }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-2 pt-2 border-top">
                                            <div class="col-4">
                                                <div
                                                    class="bg-light p-2 rounded-3 text-center shadow-sm h-100 d-flex flex-column justify-content-center">
                                                    <span class="fw-black text-uppercase d-block mb-1 text-emerald-600"
                                                        style="font-size: 0.65rem; letter-spacing: 0.5px;">GWA</span>
                                                    <span class="text-dark fw-bold" style="font-size: 0.95rem;">
                                                        <i class="bi bi-star-fill text-warning me-1"
                                                            style="font-size: 0.8rem;"></i>{{ $candidate->average_grade ?: 'N/A' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div
                                                    class="bg-light p-2 rounded-3 text-center shadow-sm h-100 d-flex flex-column justify-content-center">
                                                    <span class="fw-black text-uppercase d-block mb-1 text-emerald-600"
                                                        style="font-size: 0.65rem; letter-spacing: 0.5px;">Prev.
                                                        Project</span>
                                                    <span
                                                        class="text-dark fw-semibold small px-1 text-truncate d-block"
                                                        style="font-size: 0.8rem;"
                                                        title="{{ is_array($candidate->previous_school_project) ? implode(', ', $candidate->previous_school_project) : $candidate->previous_school_project }}">
                                                        {{ is_array($candidate->previous_school_project)
                                                            ? (implode(', ', $candidate->previous_school_project) ?:
                                                                'None')
                                                            : $candidate->previous_school_project ?? 'None' }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div
                                                    class="bg-light p-2 rounded-3 text-center shadow-sm h-100 d-flex flex-column justify-content-center">
                                                    <span class="fw-black text-uppercase d-block mb-1 text-emerald-600"
                                                        style="font-size: 0.65rem; letter-spacing: 0.5px;">Prev.
                                                        Project</span>
                                                    <span
                                                        class="text-dark fw-semibold small px-1 text-truncate d-block"
                                                        style="font-size: 0.8rem;"
                                                        title="{{ is_array($candidate->previous_position) ? implode(', ', $candidate->previous_position) : $candidate->previous_position }}">
                                                        {{ is_array($candidate->previous_position)
                                                            ? (implode(', ', $candidate->previous_position) ?:
                                                                'None')
                                                            : $candidate->previous_position ?? 'None' }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <div class="tab-pane fade" id="platform-panel-{{ $candidate->id }}" role="tabpanel">
                                <h6 class="text-uppercase text-emerald-600 fw-bold mb-3 d-flex align-items-center"
                                    style="font-size: 0.70rem; letter-spacing: 1px;">
                                    <i class="bi bi-journal-check me-2 fs-6"></i>
                                    <span>Platform Title & Tagline</span>
                                </h6>
                                <div class="p-3 rounded-3 mb-4 border border-emerald-100 bg-emerald-50/50 shadow-sm">
                                    <h6 class="fw-black text-emerald-900 mb-1" style="font-size: 0.9rem;">
                                        {{ $approvedPlatform?->title ?? 'No Title Listed' }}
                                    </h6>
                                    <p class="small fst-italic mb-0 opacity-75 text-emerald-800"
                                        style="font-size: 0.78rem;">
                                        <i class="bi bi-quote me-1"></i>
                                        {{ $approvedPlatform?->tagline ?? 'No tagline provided.' }}
                                        <i class="bi bi-quote ms-1 inline-block -scale-x-100"></i>
                                    </p>
                                </div>

                                <h6 class="text-uppercase text-emerald-600 fw-bold mb-3 d-flex align-items-center"
                                    style="font-size: 0.70rem; letter-spacing: 1px;">
                                    <i class="bi bi-list-check me-2 fs-6"></i>
                                    <span>Key Agenda Points</span>
                                </h6>

                                <div class="agenda-list pe-1" style="max-height: 140px; overflow-y: auto;">
                                    @php
                                        $platformAgenda = $approvedPlatform?->agenda;
                                        $agenda = !empty($platformAgenda)
                                            ? (is_array($platformAgenda)
                                                ? $platformAgenda
                                                : explode("\n", $platformAgenda))
                                            : [];
                                        $filteredAgenda = array_filter(array_map('trim', $agenda));
                                    @endphp

                                    @if (count($filteredAgenda) > 0)
                                        <div class="row g-2">
                                            @foreach ($filteredAgenda as $point)
                                                <div class="col-12">
                                                    <div
                                                        class="d-flex align-items-start p-2 rounded-2 bg-light-subtle border border-light shadow-sm bg-white">
                                                        <span
                                                            class="me-2 mt-0.5 rounded-circle p-1 d-inline-flex align-items-center justify-content-center bg-emerald-50 text-emerald-600"
                                                            style="width: 18px; height: 18px;">
                                                            <i class="bi bi-check2 fw-bold"
                                                                style="font-size: 0.65rem;"></i>
                                                        </span>
                                                        <span class="text-dark"
                                                            style="font-size: 0.80rem; line-height: 1.4;">
                                                            {{ $point }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-muted small fst-italic p-3 text-center bg-light rounded-3">
                                            <i class="bi bi-info-circle me-1"></i> No specific agenda details
                                            listed for this platform.
                                        </div>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    @endforeach

    @if ($this->isEligibleToEdit)
        <div class="modal fade" id="editMyPlatformModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable mx-3 mx-md-auto">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
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
                                <div class="tab-pane fade show active" id="profile-pane" role="tabpanel">
                                    <div class="row g-3">
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
                                        <div class="col-12 col-md-8">
                                            <div class="row g-2">
                                                <div class="col-6 mb-2">
                                                    <label class="small fw-bold text-primary"
                                                        style="font-size: 0.7rem;">Party Name</label>
                                                    <input type="text" wire:model="party_name"
                                                        class="form-control form-control-sm border-0 bg-light py-2"
                                                        placeholder="e.g. Alyansa ng Kabataan">
                                                </div>
                                                <div class="col-6 mb-2">
                                                    <label class="small fw-bold text-primary"
                                                        style="font-size: 0.7rem;">GWA (Optional)</label>
                                                    <input type="text" wire:model="average_grade"
                                                        class="form-control form-control-sm border-0 bg-light py-2"
                                                        placeholder="e.g. 1.75">
                                                </div>
                                                <div class="col-12">
                                                    <label class="small fw-bold text-primary"
                                                        style="font-size: 0.7rem;">Achievements (Press Enter for new
                                                        line)</label>
                                                    <textarea wire:model="achievements" rows="2" class="form-control form-control-sm border-0 bg-light"
                                                        placeholder="• With Honors (Grade 11)&#10;• Best in Public Speaking&#10;• Quiz Bee Champion 2024"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <hr class="my-3 opacity-25">
                                        <div class="col-12 col-md-6">
                                            <label class="small fw-bold text-primary mb-1 d-block"
                                                style="font-size: 0.7rem;">Previous Positions</label>
                                            @foreach ($previous_position as $index => $pos)
                                                <div class="d-flex gap-1 mb-2" wire:key="pos-{{ $index }}">
                                                    <input type="text"
                                                        wire:model="previous_position.{{ $index }}"
                                                        class="form-control form-control-sm border-0 bg-light py-1"
                                                        placeholder="e.g. SSG Treasurer">
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
                                        <div class="col-12 col-md-6">
                                            <label class="small fw-bold text-primary mb-1 d-block"
                                                style="font-size: 0.7rem;">Previous School Projects</label>
                                            @foreach ($previous_school_project as $index => $proj)
                                                <div class="d-flex gap-1 mb-2" wire:key="proj-{{ $index }}">
                                                    <input type="text"
                                                        wire:model="previous_school_project.{{ $index }}"
                                                        class="form-control form-control-sm border-0 bg-light py-1"
                                                        placeholder="e.g. Coastal Cleanup Drive">
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
                                <div class="tab-pane fade" id="platform-pane" role="tabpanel">
                                    <div class="mb-3">
                                        <label class="small fw-bold text-primary" style="font-size: 0.7rem;">Campaign
                                            Tagline</label>
                                        <input type="text" wire:model="tagline"
                                            class="form-control form-control-sm border-0 bg-light py-2"
                                            placeholder="e.g. Matapat na Serbisyo para sa Lahat">
                                        @error('tagline')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="mb-3">
                                        <label class="small fw-bold text-primary" style="font-size: 0.7rem;">Platform
                                            Title</label>
                                        <input type="text" wire:model="platform_title"
                                            class="form-control form-control-sm border-0 bg-light py-2"
                                            placeholder="e.g. THE 3-POINT AGENDA: Education, Environment, and Empowerment">
                                        @error('platform_title')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="mb-2">
                                        <label class="small fw-bold text-primary" style="font-size: 0.7rem;">Agenda
                                            Details (List your plans)</label>
                                        <textarea wire:model="agenda" rows="5" class="form-control form-control-sm border-0 bg-light"
                                            placeholder="1. Transparent Budgeting&#10;2. Student Rights Advocacy&#10;3. Mental Health Support Programs"></textarea>
                                        @error('agenda')
                                            <span class="text-danger small">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
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

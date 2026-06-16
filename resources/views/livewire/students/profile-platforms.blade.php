<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\{Layout, Title, Computed, Url};
use App\Traits\{ChecksMaintenance, AuthenticatesLogout};
use Illuminate\Support\Facades\{Auth, Storage, Log};
use App\Services\Student\ProfilePlatformService;
use App\Models\{ElectionCycle, Platform, Student, Candidate, Block, Course};

/**
 * Candidate Profiles & Platforms page for students.
 *
 * Renders a position-filtered grid of candidate introductory profiles
 * and approved platform agendas.  Eligible candidates can edit their
 * own profile and platform from a modal when filing or campaigning
 * is open.
 */
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

    /**
     * Inject the profile-platform service.
     *
     * @param  \App\Services\Student\ProfilePlatformService  $profilePlatformService
     * @return void
     */
    public function boot(ProfilePlatformService $profilePlatformService)
    {
        $this->profilePlatformService = $profilePlatformService;
    }

    /**
     * Seed the form with the current user's candidate data.
     *
     * Loads student info and, if eligible, populates the
     * profile and platform fields from the existing record.
     *
     * @return void
     */
    public function mount()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $studentData = $this->profilePlatformService->getStudentData($user);
        $this->student = $studentData['student'];
        $this->profile_photo_path = $studentData['profile_photo_path'];
    }

    /**
     * Get the active election cycle.
     *
     * @return \App\Models\ElectionCycle|null
     */
    #[Computed]
    public function activeCycle()
    {
        return $this->profilePlatformService->getActiveCycle();
    }

    /**
     * Check whether the filing period is currently open.
     *
     * @return bool
     */
    #[Computed]
    public function isFilingOpen()
    {
        return $this->profilePlatformService->isFilingOpen($this->activeCycle);
    }

    /**
     * Check whether the campaign period is currently open.
     *
     * @return bool
     */
    #[Computed]
    public function isCampaignOpen()
    {
        return $this->profilePlatformService->isCampaignOpen($this->activeCycle);
    }

    /**
     * Check whether the voting period is currently open.
     *
     * @return bool
     */
    #[Computed]
    public function isVotingOpen()
    {
        return $this->profilePlatformService->isVotingOpen($this->activeCycle);
    }

    /**
     * Get the list of positions available for the student's course.
     *
     * @return \Illuminate\Support\Collection
     */
    #[Computed]
    public function positionsList()
    {
        $courseId = $this->profilePlatformService->getStudentCourseId($this->student);
        return $this->profilePlatformService->getPositionsList($this->activeCycle, $courseId);
    }

    /**
     * Get candidates filtered by the selected position.
     *
     * @return \Illuminate\Support\Collection
     */
    #[Computed]
    public function filteredCandidates()
    {
        $courseId = $this->profilePlatformService->getStudentCourseId($this->student);
        return $this->profilePlatformService->getFilteredCandidates($this->activeCycle, $courseId, $this->selectedPosition);
    }

    /**
     * Append an empty entry to a dynamic array field (e.g. previous positions).
     *
     * @param  string  $property
     * @return void
     */
    public function addField($property)
    {
        $this->{$property}[] = '';
    }

    /**
     * Remove an entry from a dynamic array field by index.
     *
     * @param  string  $property
     * @param  int  $index
     * @return void
     */
    public function removeField($property, $index)
    {
        unset($this->{$property}[$index]);
        $this->{$property} = array_values($this->{$property});
    }

    /**
     * Set the active position filter for the candidate grid.
     *
     * @param  string  $pos
     * @return void
     */
    public function selectPosition($pos)
    {
        $this->selectedPosition = $pos;
    }

    /**
     * Validate and save the candidate's profile and platform.
     *
     * Only available to candidates when editing is unlocked
     * and voting has not started.  Dispatches a success or
     * error SweetAlert and logs the activity.
     *
     * @return void
     */
    public function updatePlatform()
    {
        if (!Auth::user()->hasRole('candidate')) {
            abort(403);
        }

        $active = $this->activeCycle;
        $isVotingStarted = $active && now()->gt($active->voting_start);

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
                    'status' => 'approved',
                    'approved_at' => now(),
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
                'text' => 'Your profile and platform have been saved and are now live.',
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

    /**
     * Generate a deterministic avatar background color for a candidate.
     *
     * @param  int  $id
     * @return string
     */
    public function getAvatarColor($id)
    {
        return $this->profilePlatformService->getAvatarColor($id);
    }
}; ?>

<div>
    {{-- Mobile header branding --}}
    <div
        class="d-lg-none d-flex align-items-center justify-content-start p-2 px-4 bg-white shadow-sm gap-2 border-bottom">
        <img src="{{ asset('images/logo.png') }}" alt="Logo" style="height: 45px; width: 45px; object-fit: contain;">

        <h4 class="mb-0 text-primary" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
            Top Link Global College, Inc.
        </h4>
    </div>

    {{-- Student sidebar navigation --}}
    @include('layouts.partials.student-sidebar')
    <main class="main-content" style="font-size: clamp(13px, 2vw + 8px, 16px);">
        {{-- Topbar: page title and edit-my-platform button --}}
        <div class="topbar">
            <div>
                <h2 class="text-dark">Candidate <span class="text-primary">Profiles & Platforms</span></h2>
                <p class="text-secondary mb-0 small">Learn about candidate advocacy before casting your vote</p>
            </div>
        </div>

        {{-- Determine phase access: voting, campaign, or filing (non-student-only) unlocks the grid --}}
        @php
            $isVoting = $this->isVotingOpen;
            $isFiling = $this->isFilingOpen;
            $isCampaign = $this->isCampaignOpen;

            $canSeeGrid = $isVoting;
        @endphp

        {{-- Candidate grid (visible when access is granted) --}}
        @if ($canSeeGrid)
            {{-- Position filter tabs --}}
            <div class="d-flex gap-1 gap-md-2 flex-wrap mb-3">
                @if (!empty($this->positionsList) && count($this->positionsList) > 0)
                    @foreach ($this->positionsList as $pos)
                        <button wire:click="selectPosition('{{ $pos }}')"
                            @click="selectedPosition = '{{ $pos }}'"
                            class="tab-custom {{ $selectedPosition === $pos ? 'active' : '' }} text-xs md:text-base px-2 py-2 md:px-3 md:py-3"
                            style="font-size: 0.8rem;">
                            {{ $pos }}
                        </button>
                    @endforeach
                @endif
            </div>

            {{-- Candidate profile cards grid --}}
            <div class="row g-2 g-md-4 mb-12 mb-md-0">
                @forelse($this->filteredCandidates as $candidate)
                    <div class="col-6 col-md-6 col-lg-4 col-xl-3 mb-2"
                        wire:key="candidate-box-{{ $selectedPosition }}-{{ $candidate->id }}">
                        @php
                            $latestPlatform = $candidate->platforms->first();

                            $suffix = $candidate->student->suffix;
                            $formattedSuffix = in_array($suffix, ['Jr', 'Sr']) ? $suffix . '.' : $suffix;
                        @endphp

                        <div
                            class="position-relative bg-white rounded-5 shadow-sm border transition-all hover-translate-y hover-shadow-lg p-4 h-100 d-flex flex-column align-items-center text-center group-card">

                            <div
                                class="position-absolute top-0 start-0 w-100 p-2 d-flex justify-content-between align-items-center">
                                <span class="badge rounded-pill bg-emerald-light text-primary fw-bold px-2 py-0.5"
                                    style="font-size: 12px; max-width: 70%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    {{ Str::limit($candidate->party_name ?? 'No Party Name', 12) }}
                                </span>

                                <button type="button" class="btn p-0 border-0" data-bs-toggle="modal"
                                    data-bs-target="#unifiedModal{{ $candidate->id }}">
                                    <i class="bi bi-info-circle-fill text-primary opacity-50"
                                        style="font-size: 0.7rem;"></i>
                                </button>
                            </div>

                            <div class="mt-4 mt-md-3 mb-1 position-relative">
                                <div class="candidate-avatar-circle shadow-sm border border-3 border-white overflow-hidden mx-auto"
                                    style="width: clamp(50px, 12vw, 90px); height: clamp(50px, 12vw, 90px); background: {{ $this->getAvatarColor($candidate->id) }};">
                                    @if ($candidate->photo)
                                        <img src="{{ asset('storage/' . $candidate->photo) }}"
                                            class="w-100 h-100 object-fit-cover">
                                    @else
                                        <div
                                            class="w-100 h-100 d-flex align-items-center justify-content-center text-white fw-bold fs-5">
                                            {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mb-1 w-100">
                                <h6 class="fw-black text-dark mb-0 text-truncate px-1"
                                    style="font-size: clamp(0.65rem, 1.8vw, 0.85rem);">
                                    {{ $candidate->student?->first_name }}
                                    {{ $candidate->student?->middle_name ? substr($candidate->student?->middle_name, 0, 1) . '.' : '' }}
                                    {{ $candidate->student?->last_name }} {{ $formattedSuffix ?? '' }}
                                </h6>
                                <p class="text-uppercase tracking-wider text-primary fw-bold mb-0"
                                    style="font-size: 0.55rem;">
                                    {{ $candidate->position?->name }}
                                </p>
                            </div>

                            <div class="bg-light rounded-4 p-1 p-md-2 mb-1 w-100">
                                <p class="text-primary mb-0 fst-italic"
                                    style="font-size: 0.65rem; line-height: 1.2; min-height: 3px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    "{{ $latestPlatform?->tagline ?? 'No Tagline' }}"
                                </p>
                            </div>

                            <div class="mt-auto w-100">
                                <button class="btn btn-outline-glow rounded-pill py-1 py-md-2 fw-bold w-100"
                                    style="font-size: 0.6rem;" data-bs-toggle="modal"
                                    data-bs-target="#unifiedModal{{ $candidate->id }}"
                                    onclick="const tab = bootstrap.Tab.getOrCreateInstance(document.querySelector('#profile-tab-{{ $candidate->id }}')); tab.show();">
                                    Profile
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    {{-- Empty state: no candidates match the current filter --}}
                    <div class="col-12 py-5 text-center">
                        <div class="p-5">
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
            {{-- Filing or campaign in progress (non-candidate view) --}}
            @if ($isFiling)
                <div class="p-5 text-center my-5 rounded-4">
                    <div class="mb-4">
                        <i class="bi bi-file-earmark-person text-primary opacity-75" style="font-size: 5rem;"></i>
                    </div>
                    <h3 class="text-primary fw-bold">Filing of Candidacy Ongoing</h3>
                    <p class="text-secondary mx-auto" style="max-width: 500px;">
                        The candidates are currently finalizing their platforms. Campaigning will start soon!
                    </p>
                </div>
            @elseif ($isCampaign)
                <div class="p-5 text-center my-5 rounded-4">
                    <div class="mb-4">
                        <i class="bi bi-megaphone text-primary opacity-75" style="font-size: 5rem;"></i>
                    </div>
                    <h3 class="text-primary fw-bold">Campaign of Candidacy Ongoing</h3>
                    <p class="text-secondary mx-auto" style="max-width: 500px;">
                        The candidates are currently campaigning. Voting will start soon!
                    </p>
                </div>
                {{-- Voting period finished banner --}}
            @elseif ($latestCycle && $latestCycle->status === 'completed')
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
            @else
                {{-- No active election cycle --}}
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
                        Please stand by until the Student Council Election Committee initiates the next voting cycle.
                    </p>
                </div>
            @endif
        @endif
    </main>

    {{-- Candidate introductory-profile + platform modal (one per candidate) --}}
    @foreach ($this->filteredCandidates as $candidate)
        @php
            $approvedPlatform = $candidate->platforms->first();

            $suffix = $candidate->student->suffix;
            $formattedSuffix = in_array($suffix, ['Jr', 'Sr']) ? $suffix . '.' : $suffix;
        @endphp

        <div class="modal fade" id="unifiedModal{{ $candidate->id }}" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg mx-3 mx-md-auto">
                <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">

                    <div class="modal-header border-0 p-3 pb-2 flex-column align-items-start bg-emerald-50">
                        <div class="d-flex justify-content-between align-items-center w-100 mb-2">
                            <span class="badge bg-white fw-bold px-2 py-1 shadow-sm text-emerald-600"
                                style="font-size: 0.7rem; letter-spacing: 0.5px;">
                                CANDIDATE PROFILE
                            </span>
                            <button type="button" class="btn-close" style="font-size: 0.7rem;"
                                data-bs-dismiss="modal"></button>
                        </div>

                        <ul class="nav nav-tabs border-0 nav-justified w-100" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link active border-0 rounded-3 py-2 fw-bold text-secondary w-100 small"
                                    id="profile-tab-{{ $candidate->id }}" data-bs-toggle="tab"
                                    data-bs-target="#profile-panel-{{ $candidate->id }}" type="button"
                                    style="font-size: 0.75rem; transition: all 0.2s ease;">
                                    <i class="bi bi-person-circle me-1"></i>Introductory Profile
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link border-0 rounded-3 py-2 fw-bold text-secondary w-100 small"
                                    id="platform-tab-{{ $candidate->id }}" data-bs-toggle="tab"
                                    data-bs-target="#platform-panel-{{ $candidate->id }}" type="button"
                                    style="font-size: 0.75rem; transition: all 0.2s ease;">
                                    <i class="bi bi-megaphone me-1"></i>Platform & Agenda
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

                                                    @php
                                                        $achievementsList = is_array($candidate->achievements)
                                                            ? $candidate->achievements
                                                            : ($candidate->achievements
                                                                ? explode(',', $candidate->achievements)
                                                                : []);

                                                        $achievementsList = array_filter(
                                                            array_map('trim', $achievementsList),
                                                        );
                                                    @endphp

                                                    @if (count($achievementsList) > 0)
                                                        <ul class="mb-0 ps-3 text-muted"
                                                            style="list-style-type: disc;">
                                                            @foreach ($achievementsList as $achievement)
                                                                <li class="mb-1">{{ $achievement }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @else
                                                        <span class="text-muted">No achievements listed.</span>
                                                    @endif

                                                </div>
                                            </div>
                                        </div>

                                        <div class="row g-2 border-top">
                                            <div class="col-6">
                                                <div
                                                    class="bg-light p-2 rounded-3 shadow-sm h-100 d-flex flex-column justify-content-start">
                                                    <span
                                                        class="fw-black text-uppercase d-block mb-1 text-emerald-600 text-center"
                                                        style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                                        Prev. Project
                                                    </span>
                                                    <div class="text-dark fw-semibold small px-1 text-wrap w-100"
                                                        style="font-size: 0.8rem; line-height: 1.3; max-height: 4.5em; overflow-y: auto;"
                                                        title="{{ is_array($candidate->previous_school_project) ? implode(', ', $candidate->previous_school_project) : $candidate->previous_school_project }}">

                                                        @php
                                                            $projectsList = is_array(
                                                                $candidate->previous_school_project,
                                                            )
                                                                ? $candidate->previous_school_project
                                                                : ($candidate->previous_school_project
                                                                    ? explode(',', $candidate->previous_school_project)
                                                                    : []);
                                                            $projectsList = array_filter(
                                                                array_map('trim', $projectsList),
                                                            );
                                                        @endphp

                                                        @if (count($projectsList) > 0)
                                                            <ul class="mb-0 ps-3 text-start"
                                                                style="list-style-type: disc;">
                                                                @foreach ($projectsList as $project)
                                                                    <li class="mb-0.5">{{ $project }}</li>
                                                                @endforeach
                                                            </ul>
                                                        @else
                                                            <span class="text-muted d-block text-center">None</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-6">
                                                <div
                                                    class="bg-light p-2 rounded-3 shadow-sm h-100 d-flex flex-column justify-content-start">
                                                    <span
                                                        class="fw-black text-uppercase d-block mb-1 text-emerald-600 text-center"
                                                        style="font-size: 0.65rem; letter-spacing: 0.5px;">
                                                        Prev. Position
                                                    </span>
                                                    <div class="text-dark fw-semibold small px-1 text-wrap w-100"
                                                        style="font-size: 0.8rem; line-height: 1.3; max-height: 4.5em; overflow-y: auto;"
                                                        title="{{ is_array($candidate->previous_position) ? implode(', ', $candidate->previous_position) : $candidate->previous_position }}">

                                                        @php
                                                            $positionsList = is_array($candidate->previous_position)
                                                                ? $candidate->previous_position
                                                                : ($candidate->previous_position
                                                                    ? explode(',', $candidate->previous_position)
                                                                    : []);
                                                            $positionsList = array_filter(
                                                                array_map('trim', $positionsList),
                                                            );
                                                        @endphp

                                                        @if (count($positionsList) > 0)
                                                            <ul class="mb-0 ps-3 text-start"
                                                                style="list-style-type: disc;">
                                                                @foreach ($positionsList as $position)
                                                                    <li class="mb-0.5">{{ $position }}</li>
                                                                @endforeach
                                                            </ul>
                                                        @else
                                                            <span class="text-muted d-block text-center">None</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-12">
                                                    <div
                                                        class="bg-light p-2 rounded-3 text-center shadow-sm h-100 d-flex flex-column justify-content-center">
                                                        <span
                                                            class="fw-black text-uppercase d-block mb-1 text-emerald-600"
                                                            style="font-size: 0.65rem; letter-spacing: 0.5px;">GWA</span>
                                                        <span class="text-dark fw-bold" style="font-size: 0.95rem;">
                                                            <i class="bi bi-star-fill text-warning me-1"
                                                                style="font-size: 0.8rem;"></i>{{ $candidate->average_grade ?: 'N/A' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade p-3" id="platform-panel-{{ $candidate->id }}" role="tabpanel">
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
</div>

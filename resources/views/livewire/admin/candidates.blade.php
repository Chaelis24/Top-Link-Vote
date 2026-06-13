<?php

use Livewire\Volt\Component;
use App\Traits\AuthenticatesLogout;
use Livewire\{WithFileUploads, WithPagination};
use App\Http\Requests\Admin\UpdateCandidateRequest;
use Livewire\Attributes\{Layout, Title, Url, Computed};
use Illuminate\Support\Facades\{Auth, Session, DB, Storage};
use App\Models\{Student, User, Candidate, Position, ElectionCycle, Platform, Course};

new #[Layout('layouts.admin')] #[Title('Manage Candidates Profile')] class extends Component {
    use WithFileUploads, WithPagination, AuthenticatesLogout;

    public $csvFile;
    public $candidate_photo;
    public $editingCandidateId;

    #[Url(history: true)]
    public string $search = '';
    #[Url(history: true)]
    public string $positionFilter = 'All Positions';
    #[Url(history: true)]
    public string $departmentFilter = 'All Departments';
    #[Url(history: true)]
    public string $selectedCycleId = 'active';

    public $editForm = [
        'first_name' => '',
        'last_name' => '',
        'party_name' => '',
        'position_id' => '',
        'achievements' => '',
        'previous_position' => [''],
        'previous_school_project' => [''],
        'average_grade' => '',
        'platform_title' => '',
        'tagline' => '',
        'agenda' => '',
        'photo_url' => '',
    ];

    // --- Computed & Magic Properties ---
    #[Computed]
    public function activeCycle()
    {
        return ElectionCycle::getActiveCycle();
    }

    public function getElectionCyclesProperty()
    {
        return ElectionCycle::orderBy('created_at', 'desc')->get();
    }

    // --- Lifecycle / Data Fetching ---
    public function with(): array
    {
        $activeCycleId = $this->activeCycle ? $this->activeCycle->id : 0;

        $stats = cache()->remember("candidates_stats_{$activeCycleId}", 120, function () use ($activeCycleId) {
            return Candidate::where('election_cycle_id', $activeCycleId)
                ->selectRaw(
                    "
                count(*) as total,
                count(case when status in ('approved', 'active') then 1 end) as approved,
                count(case when status = 'pending' then 1 end) as pending
            ",
                )
                ->first();
        });

        return [
            'candidates' => $this->loadCandidates(),
            'totalCandidates' => $stats->total ?? 0,
            'approvedCount' => $stats->approved ?? 0,
            'pendingCount' => $stats->pending ?? 0,
            'availablePositions' => $this->activeCycle ? Position::where('election_cycle_id', $this->activeCycle->id)->distinct()->pluck('name') : collect(),
            'availableDepartments' => Course::pluck('name')->toArray(),
        ];
    }

    public function loadCandidates()
    {
        $cycleId = $this->selectedCycleId === 'active' ? ($this->activeCycle ? $this->activeCycle->id : 0) : $this->selectedCycleId;

        return Candidate::query()
            ->with(['student.block.course', 'position', 'platforms'])
            ->withCount('votes')
            ->join('positions', 'candidates.position_id', '=', 'positions.id')
            ->where('candidates.election_cycle_id', $cycleId)
            ->where(function ($query) {
                if ($this->search) {
                    $query->whereHas('student', function ($q) {
                        $q->where('first_name', 'like', '%' . $this->search . '%')
                            ->orWhere('last_name', 'like', '%' . $this->search . '%')
                            ->orWhere('student_id', 'like', '%' . $this->search . '%');
                    });
                }
            })
            ->when($this->positionFilter !== 'All Positions', function ($query) {
                $query->where('positions.name', $this->positionFilter);
            })
            ->when($this->departmentFilter !== 'All Departments', function ($query) {
                $query->whereHas('student.block.course', fn($q) => $q->where('name', $this->departmentFilter));
            })
            ->orderBy('positions.priority', 'asc')
            ->orderBy('candidates.created_at', 'desc')
            ->select('candidates.*')
            ->paginate(10);
    }

    // --- Dynamic Form Fields ---
    public function addField($property)
    {
        $this->editForm[$property][] = '';
    }

    public function removeField($property, $index)
    {
        unset($this->editForm[$property][$index]);
        $this->editForm[$property] = array_values($this->editForm[$property]);

        if (empty($this->editForm[$property])) {
            $this->editForm[$property][] = '';
        }
    }

    // --- CRUD Operations ---
    public function editCandidate($id)
    {
        $this->resetErrorBag();
        $candidate = Candidate::with(['student', 'platforms'])->findOrFail($id);
        $this->editingCandidateId = $id;
        $platform = $candidate->platforms->first();
        $this->reset('candidate_photo');

        $prevPos = is_array($candidate->previous_position) ? $candidate->previous_position : json_decode($candidate->previous_position, true);
        $prevProj = is_array($candidate->previous_school_project) ? $candidate->previous_school_project : json_decode($candidate->previous_school_project, true);

        $this->editForm = [
            'first_name' => $candidate->student->first_name,
            'last_name' => $candidate->student->last_name,
            'party_name' => $candidate->party_name ?? '',
            'position_id' => $candidate->position_id,
            'achievements' => $candidate->achievements ?? '',
            'previous_position' => !empty($prevPos) ? $prevPos : [''],
            'previous_school_project' => !empty($prevProj) ? $prevProj : [''],
            'average_grade' => $candidate->average_grade ?? '',
            'platform_title' => $platform->title ?? '',
            'tagline' => $platform->tagline ?? '',
            'agenda' => is_array($platform?->agenda) ? implode("\n", $platform->agenda) : $platform->agenda ?? '',
            'existing_photo' => $candidate->photo,
        ];
        $this->dispatch('open-modal', id: 'editCandidateModal');
    }

    public function updateCandidate()
    {
        $request = new UpdateCandidateRequest();
        $this->validate($request->rules(), [], $request->attributes());
        $data = $this->editForm;

        try {
            DB::beginTransaction();

            $candidate = Candidate::findOrFail($this->editingCandidateId);

            $cleanPreviousPositions = array_values(array_filter(array_map('trim', (array) ($data['previous_position'] ?? []))));
            $cleanPreviousProjects = array_values(array_filter(array_map('trim', (array) ($data['previous_school_project'] ?? []))));

            $photoPath = $candidate->photo;

            if ($this->candidate_photo) {
                if ($candidate->photo) {
                    Storage::disk('public')->delete($candidate->photo);
                }
                $photoPath = $this->candidate_photo->store('candidates-picture', 'public');
            }

            $candidate->update([
                'party_name' => $data['party_name'],
                'position_id' => $data['position_id'],
                'achievements' => $data['achievements'],
                'previous_position' => $cleanPreviousPositions,
                'previous_school_project' => $cleanPreviousProjects,
                'average_grade' => $data['average_grade'],
                'photo' => $photoPath,
                'status' => 'approved',
            ]);

            $agendaArray = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $data['agenda'] ?? '')))));

            $candidate->platforms()->updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'title' => $data['platform_title'],
                    'tagline' => $data['tagline'],
                    'agenda' => $agendaArray,
                    'status' => 'approved',
                ],
            );

            DB::commit();
            $this->dispatch('close-modal', id: 'editCandidateModal');
            $this->dispatch('swal', [
                'title' => 'Updated!',
                'text' => 'Saved successfully.',
                'icon' => 'success',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('swal', [
                'title' => 'Error',
                'text' => $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    public function deleteCandidate($id)
    {
        try {
            Candidate::destroy($id);
            $this->dispatch('swal', [
                'title' => 'Deleted',
                'text' => 'The candidate has been removed from the records.',
                'icon' => 'info',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'title' => 'Delete Error',
                'text' => 'Could not delete candidate: ' . $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    // --- CSV Import ---
    public function importCandidates()
    {
        $this->validate(['csvFile' => 'required|file|max:5120|mimes:csv,txt']);

        try {
            $activeCycle = $this->activeCycle;

            if (!$activeCycle) {
                throw new \Exception('No active election cycle found. Please create a cycle first.');
            }

            $path = $this->csvFile->getRealPath();
            $file = fopen($path, 'r');
            fgetcsv($file);
            DB::beginTransaction();
            $importedCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                if (empty(array_filter($row))) {
                    continue;
                }

                $studentId = trim($row[0]);
                $positionName = trim($row[1]);

                $student = Student::where('student_id', $studentId)->first();

                if ($student && $student->user_id) {
                    $pos = Position::firstOrCreate(
                        [
                            'name' => $positionName,
                            'election_cycle_id' => $activeCycle?->id,
                            'student_department' => $student->block->course_id ?? null,
                        ],
                        [
                            'max_candidates' => 10,
                            'max_winners' => 1,
                            'priority' => 1,
                            'is_active' => true,
                        ],
                    );

                    $can = Candidate::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'election_cycle_id' => $activeCycle?->id,
                        ],
                        [
                            'user_id' => $student->user_id,
                            'position_id' => $pos->id,
                            'status' => 'approved',
                            'approved_at' => now(),
                        ],
                    );

                    Platform::updateOrCreate(
                        ['candidate_id' => $can->id],
                        [
                            'title' => '',
                            'status' => 'approved',
                        ],
                    );

                    $importedCount++;
                }
            }

            fclose($file);
            cache()->forget('candidates_stats_' . $activeCycle->id);
            cache()->forget('admin_dashboard_data');

            DB::commit();
            $this->reset('csvFile');
            $this->dispatch('swal', [
                'title' => 'Success',
                'text' => "Imported $importedCount candidates.",
                'icon' => 'success',
            ]);
        } catch (\Exception $e) {
            if (isset($file)) {
                fclose($file);
            }
            DB::rollBack();
            $this->dispatch('swal', [
                'title' => 'Import Failed',
                'text' => 'Process halted due to an error: ' . $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    // --- Utilities ---
    public function getAvatarColor($id)
    {
        $colors = ['#10b981', '#3b82f6', '#6366f1', '#f59e0b', '#ef4444'];
        return $colors[$id % count($colors)];
    }
}; ?>

<div>
    <div
        class="d-lg-none d-flex align-items-center justify-content-start p-2 px-4 bg-white/opacity-50 shadow-sm gap-2 border-bottom">
        <img src="{{ asset('images/logo.png') }}" alt="Logo" style="height: 45px; width: 45px; object-fit: contain;">

        <h4 class="mb-0 text-primary" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
            Top Link Global College, Inc.
        </h4>
    </div>
    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div class="topbar-info">
                <h2 class="fw-bold text-primary">Manage <span class="text-accent">Candidates Profile</span></h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Import & Update Candidate Profile</p>
            </div>
            <div x-data="{
                confirmImport(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    Swal.fire({
                        title: 'Import Candidates?',
                        text: `Process student IDs from '${file.name}'?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#1e3a8a',
                        confirmButtonText: 'Yes, import!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            @this.upload('csvFile', file, () => { @this.importCandidates(); });
                        }
                    });
                }
            }">
                <input type="file" x-ref="csvInput" class="d-none" accept=".csv" @change="confirmImport($event)">
                <x-button variant="glow" class="w-full xs:w-auto px-4 md:px-6" @click="$refs.csvInput.click()"
                    title="Import CSV">
                    <i class="bi bi-file-earmark-arrow-up fs-7 p-1"></i>

                    <span class="hidden md:inline ms-1">Import CSV</span>
                </x-button>
            </div>
        </div>

        <div class="row g-2 mb-2">
            <div class="col-6 col-md-6">
                <div class="glass-card stat-card py-2 text-center h-100">
                    <div class="stat-value text-primary" style="font-size: 1.2rem;">{{ $totalCandidates }}</div>
                    <div class="stat-label text-uppercase fw-bold" style="letter-spacing: 1px;">Total Candidates</div>
                </div>
            </div>
            <div class="col-6 col-md-6">
                <div class="glass-card stat-card py-2 text-center h-100">
                    <div class="stat-value text-success" style="font-size: 1.2rem;">{{ $approvedCount }}</div>
                    <div class="stat-label text-uppercase fw-bold" style="letter-spacing: 1px;">Active</div>
                </div>
            </div>
        </div>

        <div class="glass-card p-3 p-md-4 mb-3 border-0 shadow-sm">
            <div class="row g-2 g-md-3 align-items-center">
                <div class="col-12 col-md-3">
                    <div class="search-wrap-modern">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" wire:model.live.debounce.300ms="search" class="search-input"
                            placeholder="Search name or ID...">
                    </div>
                </div>
                <div class="col-4 col-md-3">
                    <select wire:model.live="departmentFilter" class="form-select-modern w-100"
                        style="font-size: 0.8rem; padding-left: 5px; padding-right: 5px;">
                        <option value="All Departments">💼All Depts</option>
                        @foreach ($availableDepartments as $dept)
                            <option value="{{ $dept }}">💼{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4 col-md-3">
                    <select wire:model.live="positionFilter" class="form-select-modern w-100"
                        style="font-size: 0.8rem; padding-left: 5px; padding-right: 5px;">
                        <option value="All Positions">🗳️ Positions</option>
                        @foreach ($availablePositions as $name)
                            <option value="{{ $name }}">🗳️{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4 col-md-3">
                    <select wire:model.live="selectedCycleId" class="form-select-modern w-100"
                        style="font-size: 0.8rem; padding-left: 5px; padding-right: 5px;">
                        <option value="active">📅 Current Active Cycle</option>
                        @foreach ($this->electionCycles as $cycle)
                            @if ($cycle->status !== 'active')
                                <option value="{{ $cycle->id }}">🔗 {{ $cycle->academic_year }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="glass-card p-0 overflow-hidden border-0 shadow-sm">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="p-3">Candidate Name</th>
                            <th class="p-3">Position & Party Name</th>
                            <th class="p-3">Profile Status</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="align-middle">
                        @forelse($candidates as $candidate)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="profile-avatar-sm shadow-sm text-white flex-shrink-0 d-flex align-items-center justify-content-center"
                                            style="background: {{ $this->getAvatarColor($candidate->id) }}; width: 40px; height: 40px; border-radius: 24px; overflow: hidden;">
                                            @if ($candidate->photo)
                                                <img src="{{ asset('storage/' . $candidate->photo) }}"
                                                    style="width: 100%; height: 100%; object-fit: cover;">
                                            @else
                                                {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}{{ strtoupper(substr($candidate->student?->last_name ?? '', 0, 1)) }}
                                            @endif
                                        </div>
                                        <div>
                                            <div class="text-primary fw-bold mb-0 d-flex align-items-center flex-wrap gap-2"
                                                style="font-size: 0.95rem;">
                                                <span>{{ $candidate->student->first_name }}
                                                    {{ $candidate->student->last_name }}</span>
                                                @php
                                                    $maxWinners = $candidate->position->max_winners ?? 1;
                                                    $rankedCandidates = \App\Models\Candidate::where(
                                                        'election_cycle_id',
                                                        $candidate->election_cycle_id,
                                                    )
                                                        ->where('position_id', $candidate->position_id)
                                                        ->whereIn('status', ['approved', 'active'])
                                                        ->withCount('votes')
                                                        ->orderByDesc('votes_count')
                                                        ->get();
                                                    $winningVoteCount = $rankedCandidates->first()?->votes_count ?? 0;
                                                    $winnerThreshold =
                                                        $rankedCandidates->take($maxWinners)->last()?->votes_count ?? 0;
                                                    $isWinner =
                                                        $candidate->votes_count >= $winnerThreshold &&
                                                        $candidate->votes_count > 0 &&
                                                        $candidate->votes_count >=
                                                            $winningVoteCount - ($winningVoteCount > 0 ? 0 : 0);
                                                @endphp
                                                @if ($selectedCycleId !== 'active' && $isWinner)
                                                    <span
                                                        class="badge bg-success-soft text-success x-small d-inline-flex align-items-center"
                                                        style="font-size: 0.6rem; padding: 2px 4px; font-weight: 800;">
                                                        <i class="bi bi-trophy-fill text-warning me-1"></i> WINNER
                                                        ({{ $candidate->votes_count }})
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="small text-muted fw-bold">
                                                {{ $candidate->student->student_id }} |
                                                {{ $candidate->student->block->course->name ?? 'N/A' }}
                                                <span class="text-muted">
                                                    {{ $candidate->student->block->year_level ?? '' }}-{{ $candidate->student->block->section ?? '' }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark fw-medium small">{{ $candidate->position->name }}</div>
                                    <div class="text-accent fw-bold" style="font-size: 0.72rem;">
                                        {{ $candidate->party_name ?? 'No Party Name' }}</div>
                                </td>
                                <td>
                                    <div
                                        title="{{ json_encode([
                                            'Photo' => $candidate->photo ? 'OK' : 'Missing',
                                            'Grade' => $candidate->average_grade ? 'OK' : 'Missing',
                                            'Platform' => $candidate->platforms()->first() ? 'Exists' : 'No Record',
                                        ]) }}">
                                        @if ($candidate->isProfileComplete())
                                            <span class="badge-approved text-nowrap"><i
                                                    class="bi bi-check2-circle me-1"></i>Completed</span>
                                        @else
                                            <span class="badge-pending text-nowrap"><i
                                                    class="bi bi-exclamation-triangle me-1"></i>Missing Bio</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center pe-4 text-nowrap">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        @php
                                            $active = $this->activeCycle;
                                            $now = now();

                                            $isVotingStarted = $active && $now->gt($active->voting_start);
                                            $isVotingFinished = $active && $now->gt($active->voting_end);
                                            $isLocked = $isVotingStarted || $isVotingFinished;
                                        @endphp

                                        @if ($isLocked)
                                            <button type="button" class="btn-icon btn-edit"
                                                style="background: rgba(108, 117, 125, 0.1); color: #6c757d; border: none; width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: not-allowed; opacity: 0.6;"
                                                disabled title="Voting is ongoing or finished. Edits are locked.">
                                                <i class="bi bi-lock-fill" style="font-size: 0.90rem;"></i>
                                            </button>
                                        @else
                                            <x-icon-button variant="edit"
                                                wire:click="editCandidate({{ $candidate->id }})">
                                                <i class="bi bi-pencil-square"></i>
                                            </x-icon-button>
                                        @endif

                                        <x-icon-button variant="delete" x-data
                                            @click="
                                                Swal.fire({
                                                    title: 'Delete Candidate?',
                                                    text: 'This action is permanent. All data associated with this candidate will be removed.',
                                                    icon: 'warning',
                                                    showCancelButton: true,
                                                    confirmButtonColor: 'var(--danger-red, #dc3545)',
                                                    cancelButtonColor: '#6c757d',
                                                    confirmButtonText: 'Yes, delete permanently',
                                                    cancelButtonText: 'Cancel'
                                                }).then((result) => {
                                                    if (result.isConfirmed) {
                                                        $wire.deleteCandidate({{ $candidate->id }})
                                                    }
                                                })
                                            ">
                                            <i class="bi bi-trash"></i>
                                        </x-icon-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted fst-italic">No candidate found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="md:hidden">
                @forelse($candidates as $candidate)
                    <div class="p-3 border-bottom position-relative">
                        <div class="d-flex align-items-start gap-3">
                            <div class="profile-avatar-sm shadow-sm text-white flex-shrink-0 d-flex align-items-center justify-content-center"
                                style="background: {{ $this->getAvatarColor($candidate->id) }}; width: 45px; height: 45px; border-radius: 50%; overflow: hidden;">
                                @if ($candidate->photo)
                                    <img src="{{ asset('storage/' . $candidate->photo) }}"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                @else
                                    {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}{{ strtoupper(substr($candidate->student?->last_name ?? '', 0, 1)) }}
                                @endif
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-primary fw-bold mb-0" style="font-size: 0.95rem;">
                                    {{ $candidate->student->first_name }} {{ $candidate->student->last_name }}
                                </div>
                                <div class="text-dark fw-medium small mb-1">{{ $candidate->position->name }}</div>
                                <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                    <span class="text-muted fw-bold" style="font-size: 0.75rem;">
                                        <i class="bi bi-person-badge me-1"></i>{{ $candidate->student->student_id }}
                                    </span>
                                    @if ($candidate->platforms->first()?->title)
                                        <span class="badge-approved small px-2 py-1" style="font-size: 0.7rem;"><i
                                                class="bi bi-check2-circle"></i> Complete</span>
                                    @else
                                        <span class="badge-pending small px-2 py-1" style="font-size: 0.7rem;"><i
                                                class="bi bi-exclamation-triangle"></i> Missing</span>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <x-icon-button variant="edit" wire:click="editCandidate({{ $candidate->id }})">
                                    <i class="bi bi-pencil-square"></i>
                                </x-icon-button>
                                <x-icon-button variant="delete" x-data
                                    @click="
                                        Swal.fire({
                                            title: 'Delete this candidate?',
                                            text: 'This is permanent.',
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonColor: '#dc3545',
                                            cancelButtonColor: '#6c757d',
                                            confirmButtonText: 'Delete'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                $wire.deleteCandidate({{ $candidate->id }})
                                            }
                                        })
                                    ">
                                    <i class="bi bi-trash"></i>
                                </x-icon-button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted fst-italic">No records found.</div>
                @endforelse
            </div>
        </div>
        <div class="custom-pagination">
            {{ $candidates->links('layouts.partials.custom-pagination') }}
        </div>
    </main>

    <div class="modal fade" id="editCandidateModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-md modal-lg modal-dialog-scrollable mx-2 mx-md-auto">
            <div class="modal-content border-0 shadow-lg rounded-3 rounded-md-4 overflow-hidden">
                <div class="modal-header border-0 p-3 pb-2 flex-column align-items-start bg-blue-50">
                    <div class="d-flex justify-content-between align-items-center w-100 mb-2">
                        <span class="badge bg-white fw-bold px-2 py-1 shadow-sm text-blue-600"
                            style="font-size: 0.7rem; letter-spacing: 0.5px;">
                            <i class="bi bi-person-badge-fill me-1"></i> EDIT CANDIDATE DETAILS
                        </span>
                        <button type="button" class="btn-close" style="font-size: 0.7rem;"
                            data-bs-dismiss="modal"></button>
                    </div>

                    <ul class="nav nav-pills w-100 p-1 rounded-3 bg-transparent" id="editTabs" role="tablist">
                        <li class="nav-item flex-fill text-center" role="presentation">
                            <button
                                class="nav-link active fw-bold border-0 w-100 py-2 small rounded-3 !text-blue-700 bg-transparent [&.active]:!bg-blue-600 [&.active]:!text-white"
                                id="basic-tab" data-bs-toggle="tab" data-bs-target="#tab-basic" type="button"
                                role="tab" style="font-size: 0.75rem; transition: all 0.2s ease;">
                                <i class="bi bi-person-circle me-1"></i> BASIC INFO
                            </button>
                        </li>
                        <li class="nav-item flex-fill text-center" role="presentation">
                            <button
                                class="nav-link fw-bold border-0 w-100 py-2 small rounded-3 !text-blue-700 bg-transparent [&.active]:!bg-blue-600 [&.active]:!text-white"
                                id="vision-tab" data-bs-toggle="tab" data-bs-target="#tab-vision" type="button"
                                role="tab" style="font-size: 0.75rem; transition: all 0.2s ease;">
                                <i class="bi bi-megaphone me-1"></i> PLATFORM
                            </button>
                        </li>
                    </ul>
                </div>

                <form wire:submit.prevent="updateCandidate">
                    <div class="modal-body p-3 p-md-4 bg-white">
                        <div class="tab-content" id="editTabsContent">
                            <div class="tab-pane fade show active" id="tab-basic" role="tabpanel">
                                <div class="modal-form-scrollable px-1"
                                    style="max-height: 380px; overflow-y: auto; overflow-x: hidden;">
                                    <div class="row g-2 g-md-3">
                                        <div class="col-12 col-md-4 mb-2 text-center border-0 border-md-end">
                                            <label class="small fw-bold text-primary d-block mb-2">CANDIDATE
                                                PHOTO</label>
                                            <div class="text-center">
                                                <input type="file" id="photoInput" wire:model="candidate_photo"
                                                    class="d-none" accept="image/*">
                                                <label for="photoInput"
                                                    class="mx-auto shadow-sm d-flex align-items-center justify-content-center position-relative profile-upload-circle"
                                                    style="width: 110px; height: 110px; border-radius: 50%; overflow: hidden; cursor: pointer; border: 4px solid #e9ecef; background: #f8fafc;">

                                                    @if ($candidate_photo)
                                                        <img src="{{ $candidate_photo->temporaryUrl() }}"
                                                            class="w-100 h-100 object-fit-cover">
                                                    @elseif (isset($editForm['existing_photo']) && $editForm['existing_photo'])
                                                        <img src="{{ asset('storage/' . $editForm['existing_photo']) }}"
                                                            class="w-100 h-100 object-fit-cover">
                                                    @else
                                                        <i class="bi bi-camera-fill fs-2 text-primary"></i>
                                                    @endif
                                                </label>
                                                <small class="text-primary d-block mt-2 fw-bold text-uppercase"
                                                    style="font-size: 9px;">Tap to change</small>
                                                @error('candidate_photo')
                                                    <span class="text-danger d-block mt-1"
                                                        style="font-size: 0.65rem;">{{ $message }}</span>
                                                @enderror
                                            </div>
                                        </div>

                                        <div class="col-12 col-md-8">
                                            <div class="row g-2">
                                                <div class="col-6 mb-1">
                                                    <label class="small fw-bold text-primary"
                                                        style="font-size: 0.7rem;">FIRST NAME</label>
                                                    <input type="text" wire:model="editForm.first_name"
                                                        class="form-control form-control-sm border-0 bg-light py-1"
                                                        placeholder="Juan" readonly>
                                                </div>
                                                <div class="col-6 mb-1">
                                                    <label class="small fw-bold text-primary"
                                                        style="font-size: 0.7rem;">LAST NAME</label>
                                                    <input type="text" wire:model="editForm.last_name"
                                                        class="form-control form-control-sm border-0 bg-light py-1"
                                                        placeholder="Dela Cruz" readonly>
                                                </div>
                                                <div class="col-6 mb-1">
                                                    <label class="small fw-bold text-primary"
                                                        style="font-size: 0.7rem;">PARTY NAME</label>
                                                    <input type="text" wire:model="editForm.party_name"
                                                        placeholder="e.g. Reform Party"
                                                        class="form-control form-control-sm border-0 bg-light py-1 @error('editForm.party_name') is-invalid @enderror">
                                                    @error('editForm.party_name')
                                                        <div class="invalid-feedback d-block"
                                                            style="font-size: 0.65rem; margin-top: 2px;">
                                                            <i class="bi bi-exclamation-circle-fill"></i>
                                                            {{ $message }}
                                                        </div>
                                                    @enderror
                                                </div>
                                                <div class="col-6 mb-1">
                                                    <label class="small fw-bold text-primary"
                                                        style="font-size: 0.7rem;">AVG GRADE (GWA)</label>
                                                    <input type="text" wire:model="editForm.average_grade"
                                                        placeholder="e.g. 1.25"
                                                        class="form-control form-control-sm border-0 bg-light py-2 @error('editForm.average_grade') is-invalid @enderror">
                                                    @error('editForm.average_grade')
                                                        <div class="invalid-feedback d-block"
                                                            style="font-size: 0.65rem; margin-top: 2px;">
                                                            <i class="bi bi-exclamation-circle-fill"></i>
                                                            {{ $message }}
                                                        </div>
                                                    @enderror
                                                </div>
                                                <div class="col-12">
                                                    <label class="small fw-bold text-primary"
                                                        style="font-size: 0.7rem;">ACHIEVEMENTS</label>
                                                    <textarea wire:model="editForm.achievements" rows="3"
                                                        placeholder="List down honors, awards, or recognitions..."
                                                        class="form-control form-control-sm border-0 bg-light @error('editForm.achievements') is-invalid @enderror"></textarea>
                                                    @error('editForm.achievements')
                                                        <span class="text-danger d-block mt-1"
                                                            style="font-size: 0.65rem;">{{ $message }}</span>
                                                    @enderror
                                                </div>

                                                <div class="col-12 mt-2">
                                                    <div class="row g-3">
                                                        <div class="col-12 col-md-6">
                                                            <label class="small fw-bold text-primary mb-1 d-block"
                                                                style="font-size: 0.7rem;">PREVIOUS POSITIONS</label>
                                                            @foreach ($editForm['previous_position'] as $index => $pos)
                                                                <div class="mb-2"
                                                                    wire:key="pos-{{ $index }}">
                                                                    <div class="d-flex gap-2">
                                                                        <input type="text"
                                                                            wire:model="editForm.previous_position.{{ $index }}"
                                                                            class="form-control form-control-sm border-0 bg-light py-2 @error('editForm.previous_position.' . $index) is-invalid @enderror"
                                                                            placeholder="e.g. Class President">
                                                                        @if (count($editForm['previous_position']) > 1)
                                                                            <button type="button"
                                                                                wire:click="removeField('previous_position', {{ $index }})"
                                                                                class="btn btn-sm text-danger p-0">
                                                                                <i class="bi bi-x-circle-fill"></i>
                                                                            </button>
                                                                        @endif
                                                                    </div>
                                                                    @error("editForm.previous_position.$index")
                                                                        <span class="text-danger d-block mt-1"
                                                                            style="font-size: 0.65rem;">{{ $message }}</span>
                                                                    @enderror
                                                                </div>
                                                            @endforeach
                                                            <button type="button"
                                                                wire:click="addField('previous_position')"
                                                                class="btn btn-sm btn-link text-primary p-0 text-decoration-none"
                                                                style="font-size: 0.7rem;">
                                                                <i class="bi bi-plus-lg"></i> Add Position
                                                            </button>
                                                        </div>

                                                        <div class="col-12 col-md-6">
                                                            <label class="small fw-bold text-primary mb-1 d-block"
                                                                style="font-size: 0.7rem;">PREVIOUS SCHOOL
                                                                PROJECTS</label>
                                                            @foreach ($editForm['previous_school_project'] as $index => $proj)
                                                                <div class="mb-2"
                                                                    wire:key="proj-{{ $index }}">
                                                                    <div class="d-flex gap-2">
                                                                        <input type="text"
                                                                            wire:model="editForm.previous_school_project.{{ $index }}"
                                                                            class="form-control form-control-sm border-0 bg-light py-2 @error('editForm.previous_school_project.' . $index) is-invalid @enderror"
                                                                            placeholder="e.g. Tree Planting Drive">
                                                                        @if (count($editForm['previous_school_project']) > 1)
                                                                            <button type="button"
                                                                                wire:click="removeField('previous_school_project', {{ $index }})"
                                                                                class="btn btn-sm text-danger p-0">
                                                                                <i class="bi bi-x-circle-fill"></i>
                                                                            </button>
                                                                        @endif
                                                                    </div>
                                                                    @error("editForm.previous_school_project.$index")
                                                                        <span class="text-danger d-block mt-1"
                                                                            style="font-size: 0.65rem;">{{ $message }}</span>
                                                                    @enderror
                                                                </div>
                                                            @endforeach
                                                            <button type="button"
                                                                wire:click="addField('previous_school_project')"
                                                                class="btn btn-sm btn-link text-primary p-0 text-decoration-none"
                                                                style="font-size: 0.7rem;">
                                                                <i class="bi bi-plus-lg"></i> Add Project
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tab-vision" role="tabpanel">
                                <div class="mb-3">
                                    <label class="small fw-bold text-primary" style="font-size: 0.7rem;">PLATFORM
                                        TITLE</label>
                                    <input type="text" wire:model="editForm.platform_title"
                                        placeholder="e.g. Educational Empowerment for All"
                                        class="form-control form-control-sm border-0 bg-light py-2 @error('editForm.platform_title') is-invalid @enderror">
                                    @error('editForm.platform_title')
                                        <span class="text-danger d-block mt-1"
                                            style="font-size: 0.65rem;">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="small fw-bold text-primary" style="font-size: 0.7rem;">CAMPAIGN
                                        TAGLINE</label>
                                    <input type="text" wire:model="editForm.tagline"
                                        placeholder="e.g. 'A Leader Who Listens, A Voice for Students'"
                                        class="form-control form-control-sm border-0 bg-light py-2 fst-italic @error('editForm.tagline') is-invalid @enderror">
                                    @error('editForm.tagline')
                                        <span class="text-danger d-block mt-1"
                                            style="font-size: 0.65rem;">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="mb-2">
                                    <label class="small fw-bold text-primary" style="font-size: 0.7rem;">AGENDA
                                        DETAILS</label>
                                    <textarea wire:model="editForm.agenda" rows="6"
                                        placeholder="Describe your goals, plans, and strategies in detail..."
                                        class="form-control form-control-sm border-0 bg-light @error('editForm.agenda') is-invalid @enderror"></textarea>
                                    @error('editForm.agenda')
                                        <span class="text-danger d-block mt-1"
                                            style="font-size: 0.65rem;">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 bg-light p-3">
                        <x-button type="button" variant="gray" data-bs-dismiss="modal" class="w-25 w-sm-initial"
                            style="min-width: 90px; max-width: 130px;">
                            Cancel
                        </x-button>
                        <x-button type="submit" variant="glow" wire:loading.attr="disabled"
                            wire:target="updateCandidate" class="w-50 w-sm-initial"
                            style="min-width: 120px; max-width: 150px;">
                            <span wire:loading.remove wire:target="updateCandidate">Save Changes</span>
                            <span wire:loading wire:target="updateCandidate">Saving...</span>
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

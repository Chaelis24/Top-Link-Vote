<?php

use Livewire\Volt\Component;
use Livewire\{WithFileUploads, WithPagination};
use Livewire\Attributes\{Layout, Title, Url, Computed};
use Illuminate\Support\Facades\{Auth, Session, DB, Storage};
use App\Models\{Student, User, Candidate, Position, ElectionCycle, Platform};

new #[Layout('layouts.admin')] #[Title('Manage Candidates Profile')] class extends Component {
    use WithFileUploads, WithPagination;

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

    public const DEPARTMENTS = ['IT', 'HRMT', 'ECT', 'HST'];

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

    public function getElectionCyclesProperty()
    {
        return ElectionCycle::orderBy('created_at', 'desc')->get();
    }

    public function with(): array
    {
        $activeCycleId = $this->activeCycle ? $this->activeCycle->id : 0;

        $stats = cache()->remember("candidates_stats_{$activeCycleId}", 120, function () use ($activeCycleId) {
            return Candidate::where('election_cycle_id', $activeCycleId) // Filter by cycle
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
            'availableDepartments' => self::DEPARTMENTS,
        ];
    }

    #[Computed]
    public function activeCycle()
    {
        return ElectionCycle::where('status', 'active')->latest()->first();
    }

    public function loadCandidates()
    {
        $cycleId = $this->selectedCycleId === 'active' ? ($this->activeCycle ? $this->activeCycle->id : 0) : $this->selectedCycleId;

        return Candidate::query()
            ->with(['student', 'position', 'platforms'])
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
                $query->whereHas('student', fn($q) => $q->where('course', $this->departmentFilter));
            })
            ->orderBy('positions.priority', 'asc')
            ->orderBy('candidates.created_at', 'desc')
            ->select('candidates.*')
            ->paginate(10);
    }

    public function editCandidate($id)
    {
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
        $this->validate([
            'editForm.party_name' => 'required|string|max:255',
            'editForm.position_id' => 'required|exists:positions,id',
            'editForm.platform_title' => 'required|string|max:255',
            'editForm.previous_position.*' => 'nullable|string|max:100',
            'editForm.previous_school_project.*' => 'nullable|string|max:100',
            'editForm.achievements' => 'nullable|string',
            'editForm.average_grade' => 'nullable|string|max:10',
            'editForm.tagline' => 'nullable|string|max:255',
            'editForm.agenda' => 'nullable|string',
            'candidate_photo' => 'nullable|image|max:2048',
        ]);

        try {
            DB::beginTransaction();

            $candidate = Candidate::findOrFail($this->editingCandidateId);

            $cleanPreviousPositions = array_values(array_filter(array_map('trim', (array) ($this->editForm['previous_position'] ?? []))));
            $cleanPreviousProjects = array_values(array_filter(array_map('trim', (array) ($this->editForm['previous_school_project'] ?? []))));

            $photoPath = $candidate->photo;

            if ($this->candidate_photo) {
                if ($candidate->photo) {
                    Storage::disk('public')->delete($candidate->photo);
                }
                $photoPath = $this->candidate_photo->store('candidates-picture', 'public');
            }

            $candidateData = [
                'party_name' => $this->editForm['party_name'],
                'position_id' => $this->editForm['position_id'],
                'achievements' => $this->editForm['achievements'],
                'previous_position' => $cleanPreviousPositions,
                'previous_school_project' => $cleanPreviousProjects,
                'average_grade' => $this->editForm['average_grade'],
                'photo' => $photoPath,
                'status' => 'approved',
                'approved_at' => $candidate->approved_at ?? now(),
            ];

            $candidate->update($candidateData);

            $agendaContent = $this->editForm['agenda'] ?? '';
            $agendaArray = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $agendaContent)))));

            $candidate->platforms()->updateOrCreate(
                ['candidate_id' => $candidate->id],
                [
                    'title' => $this->editForm['platform_title'],
                    'tagline' => $this->editForm['tagline'],
                    'agenda' => $agendaArray,
                    'status' => 'approved',
                ],
            );

            DB::commit();

            $this->dispatch('close-modal', id: 'editCandidateModal');
            $this->dispatch('swal', [
                'title' => 'Updated!',
                'text' => 'Candidate profile saved successfully.',
                'icon' => 'success',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->dispatch('swal', [
                'title' => 'Update Failed',
                'text' => 'An error occurred while saving: ' . $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    public function importCandidates()
    {
        $this->validate(['csvFile' => 'required|file|max:5120|mimes:csv,txt']);

        try {
            $activeCycle = $this->activeCycle;

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
                            'student_department' => $student->course,
                        ],
                        [
                            'max_candidate' => 10,
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

    public function getAvatarColor()
    {
        return '#3b82f6';
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
        return redirect()->route('admin.login');
    }
}; ?>

<div>
    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div class="topbar-info">
                <h2 class="fw-bold text-primary">Manage <span class="text-accent">Candidates Profile</span></h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Import & Update Candidate Profile
                </p>
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

                <button class="btn-glow" @click="$refs.csvInput.click()" title="Import CSV">
                    <i class="bi bi-file-earmark-arrow-up fs-7 p-1"></i>
                    <span class="d-none d-md-inline ms-1">Import CSV</span>
                </button>
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
                        <option value="All Departments">🏢 All Depts</option>
                        @foreach ($availableDepartments as $dept)
                            <option value="{{ $dept }}">{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-4 col-md-3">
                    <select wire:model.live="positionFilter" class="form-select-modern w-100"
                        style="font-size: 0.8rem; padding-left: 5px; padding-right: 5px;">
                        <option value="All Positions">👔 Positions</option>
                        @foreach ($availablePositions as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-4 col-md-3">
                    <select wire:model.live="selectedCycleId" class="form-select-modern w-100"
                        style="font-size: 0.8rem; padding-left: 5px; padding-right: 5px;">
                        <option value="active">📅 Current Active Cycle</option>
                        @foreach ($this->electionCycles as $cycle)
                            @if ($cycle->status !== 'active')
                                <option value="{{ $cycle->id }}">📜 {{ $cycle->cycle_name }}</option>
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
                            <th class="ps-4 py-3">Candidate Name</th>
                            <th>Position / Party Name</th>
                            <th>Profile Readiness</th>
                            <th class="text-center pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="align-middle">
                        @forelse($candidates as $candidate)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="d-flex align-items-center justify-content-center fw-bold text-white shadow-sm flex-shrink-0"
                                            style="background: {{ $this->getAvatarColor($candidate->id) }}; width: 40px; height: 40px; border-radius: 24px; overflow: hidden;">
                                            @if ($candidate->photo)
                                                <img src="{{ asset('storage/' . $candidate->photo) }}"
                                                    style="width: 100%; height: 100%; object-fit: cover;">
                                            @elseif ($candidate->student?->photo)
                                                <img src="{{ asset('storage/' . $candidate->student->photo) }}"
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

                                                @if ($selectedCycleId !== 'active')
                                                    @php
                                                        $hasHigher = \App\Models\Candidate::where(
                                                            'election_cycle_id',
                                                            $candidate->election_cycle_id,
                                                        )
                                                            ->where('position_id', $candidate->position_id)
                                                            ->where('id', '!=', $candidate->id)
                                                            ->withCount('votes')
                                                            ->get()
                                                            ->max('votes_count');

                                                        $isWinner =
                                                            $candidate->votes_count >= $hasHigher &&
                                                            $candidate->votes_count > 0;
                                                    @endphp

                                                    @if ($isWinner)
                                                        <span
                                                            class="badge bg-success-soft text-success x-small d-inline-flex align-items-center"
                                                            style="font-size: 0.6rem; padding: 2px 4px; font-weight: 800;">
                                                            <i class="bi bi-trophy-fill text-warning me-1"></i> WINNER
                                                            ({{ $candidate->votes_count }})
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                            <div class="small text-muted fw-bold">
                                                {{ $candidate->student->student_id }} |
                                                {{ $candidate->student->course }}
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
                                                    class="bi bi-check2-circle me-1"></i>Complete</span>
                                        @else
                                            <span class="badge-pending text-nowrap"><i
                                                    class="bi bi-exclamation-triangle me-1"></i>Missing Bio</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center pe-4 text-nowrap">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <button type="button" class="btn-icon btn-edit"
                                            wire:click="editCandidate({{ $candidate->id }})"
                                            style="background: rgba(13, 110, 253, 0.1); color: #0d6efd; border: none; width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-pencil-square" style="font-size: 0.90rem;"></i>
                                        </button>
                                        <button type="button" class="btn-icon btn-delete"
                                            x-on:click="
                                            Swal.fire({
                                                title: 'Delete Candidate?',
                                                text: 'This action is permanent. All data associated with this candidate will be removed.',
                                                icon: 'warning',
                                                showCancelButton: true,
                                                confirmButtonColor: '#dc3545',
                                                cancelButtonColor: '#6c757d',
                                                confirmButtonText: 'Yes, delete permanently',
                                                cancelButtonText: 'Cancel'
                                            }).then((result) => {
                                                if (result.isConfirmed) {
                                                    $wire.deleteCandidate({{ $candidate->id }})
                                                }
                                            })"
                                            style="background: rgba(220, 53, 69, 0.1); color: #dc3545; border: none; width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="bi bi-trash" style="font-size: 0.90rem;"></i>
                                        </button>
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
                            <div class="d-flex align-items-center justify-content-center fw-bold text-white shadow-sm flex-shrink-0"
                                style="background: {{ $this->getAvatarColor($candidate->id) }}; width: 45px; height: 45px; border-radius: 50%; overflow: hidden;">
                                @if ($candidate->photo)
                                    <img src="{{ asset('storage/' . $candidate->photo) }}"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                @elseif ($candidate->student?->photo)
                                    <img src="{{ asset('storage/' . $candidate->student->photo) }}"
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
                                    @php $hasPlatform = $candidate->platforms->first()?->platform_title; @endphp
                                    @if ($hasPlatform)
                                        <span class="badge-approved small px-2 py-1" style="font-size: 0.7rem;"><i
                                                class="bi bi-check2-circle"></i> Complete</span>
                                    @else
                                        <span class="badge-pending small px-2 py-1" style="font-size: 0.7rem;"><i
                                                class="bi bi-exclamation-triangle"></i> Missing</span>
                                    @endif
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <button type="button"
                                    class="btn btn-sm d-flex align-items-center justify-content-center"
                                    wire:click="editCandidate({{ $candidate->id }})"
                                    style="background: rgba(13, 110, 253, 0.1); color: #0d6efd; border: none; width: 39px; height: 39px; border-radius: 10px;"
                                    title="Edit">
                                    <i class="bi bi-pencil-square" style="font-size: 1.2rem;"></i>
                                </button>
                                <button type="button"
                                    class="btn btn-sm d-flex align-items-center justify-content-center"
                                    x-on:click="
                                    Swal.fire({
                                        title: 'Delete this candidate?',
                                        text: 'This is permanent.',
                                        icon: 'warning',
                                        showCancelButton: true,
                                        confirmButtonColor: '#dc3545',
                                        cancelButtonColor: '#6c757d',
                                        confirmButtonText: 'Delete',
                                        cancelButtonText: 'No',
                                        customClass: {
                                            popup: 'rounded-4',
                                            confirmButton: 'rounded-3',
                                            cancelButton: 'rounded-3'
                                        }
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            $wire.deleteCandidate({{ $candidate->id }})
                                        }
                                    })"
                                    style="background: rgba(220, 53, 69, 0.1); color: #dc3545; border: none; width: 39px; height: 39px; border-radius: 10px;"
                                    title="Delete">
                                    <i class="bi bi-trash" style="font-size: 1.2rem;"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-5 text-muted fst-italic">No records found.</div>
                @endforelse
            </div>
            <div class="custom-pagination">
                {{ $candidates->links('layouts.partials.custom-pagination') }}
            </div>
        </div>
    </main>

    <div class="modal fade" id="editCandidateModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-md modal-lg-lg modal-dialog-scrollable mx-2 mx-md-auto">
            <div class="modal-content border-0 shadow-lg rounded-3 rounded-md-4 overflow-hidden">
                <div class="modal-header bg-primary text-white border-0 pb-0 pt-3 px-3 px-md-4">
                    <div class="w-100">
                        <div class="d-flex justify-content-between align-items-center mb-2 mb-md-3">
                            <h6 class="modal-title fw-bold mb-0">
                                <i class="bi bi-person-badge-fill me-2"></i>Edit Candidate Details
                            </h6>
                            <button type="button" class="btn-close btn-close-white" style="font-size: 0.8rem;"
                                data-bs-dismiss="modal"></button>
                        </div>
                        <ul class="nav nav-tabs border-0 nav-justified w-100" id="editTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link active border-0 rounded-0 py-2 fw-bold text-white w-100 small opacity-75"
                                    id="basic-tab" data-bs-toggle="tab" data-bs-target="#tab-basic" type="button"
                                    role="tab"
                                    style="background: transparent; border-bottom: 3px solid white !important;">
                                    <i class="bi bi-person-circle me-1"></i>BASIC INFO
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button
                                    class="nav-link border-0 rounded-0 py-2 fw-bold text-white w-100 small opacity-75"
                                    id="vision-tab" data-bs-toggle="tab" data-bs-target="#tab-vision" type="button"
                                    role="tab" style="background: transparent;">
                                    <i class="bi bi-megaphone me-1"></i>PLATFORM
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>

                <form wire:submit.prevent="updateCandidate">
                    <div class="modal-body p-3 p-md-4 bg-white">
                        <div class="tab-content" id="editTabsContent">
                            <div class="tab-pane fade show active" id="tab-basic" role="tabpanel">
                                <div class="modal-form-scrollable px-1"
                                    style="max-height: 380px; overflow-y: auto; overflow-x: hidden;">
                                    <div class="row g-2 md-g-3">
                                        <div class="col-12 col-md-4 mb-2 text-center border-md-end">
                                            <label class="small fw-bold text-primary d-block mb-2">CANDIDATE
                                                PHOTO</label>
                                            <div class="text-center">
                                                <input type="file" id="photoInput" wire:model="candidate_photo"
                                                    class="d-none" accept="image/*">
                                                <label for="photoInput"
                                                    class="mx-auto shadow-sm d-flex align-items-center justify-content-center position-relative profile-upload-circle"
                                                    style="width: 110px; height: 110px; border-radius: 50%; overflow: hidden; cursor: pointer; border: 4px solid #e9ecef; background: #f8fafc;">

                                                    @if ($candidate_photo)
                                                        <img src="{{ $photo->temporaryUrl() }}"
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
                                                @error('photo')
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
                                                        class="form-control form-control-sm border-0 bg-light py-1">
                                                </div>
                                                <div class="col-6 mb-1">
                                                    <label class="small fw-bold text-primary"
                                                        style="font-size: 0.7rem;">AVG
                                                        GRADE (GWA)</label>
                                                    <input type="text" wire:model="editForm.average_grade"
                                                        placeholder="e.g. 1.25"
                                                        class="form-control form-control-sm border-0 bg-light py-2">
                                                </div>
                                                <div class="col-12">
                                                    <label class="small fw-bold text-primary"
                                                        style="font-size: 0.7rem;">ACHIEVEMENTS</label>
                                                    <textarea wire:model="editForm.achievements" rows="3"
                                                        placeholder="List down honors, awards, or recognitions..." class="form-control form-control-sm border-0 bg-light"></textarea>
                                                </div>
                                                <div class="row g-3 mt-2">
                                                    <div class="col-12 col-md-6">
                                                        <label class="small fw-bold text-primary mb-1 d-block"
                                                            style="font-size: 0.7rem;">PREVIOUS POSITIONS</label>

                                                        @foreach ($editForm['previous_position'] as $index => $pos)
                                                            <div class="d-flex gap-2 mb-2"
                                                                wire:key="pos-{{ $index }}">
                                                                <input type="text"
                                                                    wire:model="editForm.previous_position.{{ $index }}"
                                                                    class="form-control form-control-sm border-0 bg-light py-2"
                                                                    placeholder="e.g. Class President">

                                                                @if (count($editForm['previous_position']) > 1)
                                                                    <button type="button"
                                                                        wire:click="removeField('previous_position', {{ $index }})"
                                                                        class="btn btn-sm text-danger p-0">
                                                                        <i class="bi bi-x-circle-fill"></i>
                                                                    </button>
                                                                @endif
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
                                                            style="font-size: 0.7rem;">PREVIOUS SCHOOL PROJECTS</label>

                                                        @foreach ($editForm['previous_school_project'] as $index => $proj)
                                                            <div class="d-flex gap-2 mb-2"
                                                                wire:key="proj-{{ $index }}">
                                                                <input type="text"
                                                                    wire:model="editForm.previous_school_project.{{ $index }}"
                                                                    class="form-control form-control-sm border-0 bg-light py-2"
                                                                    placeholder="e.g. Tree Planting Drive">
                                                                @if (count($editForm['previous_school_project']) > 1)
                                                                    <button type="button"
                                                                        wire:click="removeField('previous_school_project', {{ $index }})"
                                                                        class="btn btn-sm text-danger p-0">
                                                                        <i class="bi bi-x-circle-fill"></i>
                                                                    </button>
                                                                @endif
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

                            <div class="tab-pane fade" id="tab-vision" role="tabpanel">
                                <div class="mb-3">
                                    <label class="small fw-bold text-primary" style="font-size: 0.7rem;">PLATFORM
                                        TITLE</label>
                                    <input type="text" wire:model="editForm.platform_title"
                                        placeholder="e.g. Educational Empowerment for All"
                                        class="form-control form-control-sm border-0 bg-light py-2">
                                </div>
                                <div class="mb-3">
                                    <label class="small fw-bold text-primary" style="font-size: 0.7rem;">CAMPAIGN
                                        TAGLINE</label>
                                    <input type="text" wire:model="editForm.tagline"
                                        placeholder="e.g. 'A Leader Who Listens, A Voice for Students'"
                                        class="form-control form-control-sm border-0 bg-light py-2 fst-italic">
                                </div>
                                <div class="mb-2">
                                    <label class="small fw-bold text-primary" style="font-size: 0.7rem;">AGENDA
                                        DETAILS</label>
                                    <textarea wire:model="editForm.agenda" rows="6"
                                        placeholder="Describe your goals, plans, and strategies in detail..."
                                        class="form-control form-control-sm border-0 bg-light"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light p-3">
                        <button type="button" class="btn btn-secondary btn-sm px-4 pill fw-bold shadow-sm"
                            data-bs-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm px-4 pill fw-bold shadow-sm"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="updateCandidate">Save Changes</span>
                            <span wire:loading wire:target="updateCandidate">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Saving...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

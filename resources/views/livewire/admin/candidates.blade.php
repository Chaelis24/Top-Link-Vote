<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Url, Computed};
use Livewire\{WithFileUploads, WithPagination};
use Illuminate\Support\Facades\{Auth, Session, DB};
use App\Models\{Student, User, Candidate, Position, ElectionCycle, Platform};

new #[Layout('layouts.admin')] #[Title('Manage Candidates Profile')] class extends Component {
    use WithFileUploads, WithPagination;

    public $csvFile;
    public $photo;
    public $editingCandidateId;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $positionFilter = 'All Positions';

    #[Url(history: true)]
    public string $departmentFilter = 'All Departments';

    public const DEPARTMENTS = ['IT', 'HRMT', 'ECT', 'HST'];

    public $editForm = [
        'first_name' => '',
        'last_name' => '',
        'party_name' => '',
        'position_id' => '',
        'achievements' => '',
        'previous_position' => [''],
        'average_grade' => '',
        'platform_title' => '',
        'tagline' => '',
        'agenda' => '',
        'photo_url' => '',
    ];

    public function with(): array
    {
        return [
            'candidates' => $this->loadCandidates(),
            'totalCandidates' => Candidate::count(),
            'approvedCount' => Candidate::whereIn('status', ['approved', 'active'])->count(),
            'pendingCount' => Candidate::where('status', 'pending')->count(),
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
        return Candidate::with(['student', 'position', 'platforms'])
            ->join('positions', 'candidates.position_id', '=', 'positions.id')
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
                $query->whereHas('position', fn($q) => $q->where('name', $this->positionFilter));
            })
            ->when($this->departmentFilter !== 'All Departments', function ($query) {
                $query->whereHas('student', fn($q) => $q->where('course', $this->departmentFilter));
            })
            ->orderBy('positions.priority', 'asc')
            ->select('candidates.*')
            ->paginate(10);
    }

    public function editCandidate($id)
    {
        $candidate = Candidate::with(['student', 'platforms'])->findOrFail($id);
        $this->editingCandidateId = $id;
        $platform = $candidate->platforms->first();
        $this->reset('photo');

        $this->editForm = [
            'first_name' => $candidate->student->first_name,
            'last_name' => $candidate->student->last_name,
            'party_name' => $candidate->party_name ?? '',
            'position_id' => $candidate->position_id,
            'achievements' => $candidate->achievements ?? '',
            'previous_position' => is_array($candidate->previous_position) ? $candidate->previous_position : json_decode($candidate->previous_position, true) ?? [''],
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
            'editForm.first_name' => 'required|string|max:255',
            'editForm.last_name' => 'required|string|max:255',
            'editForm.party_name' => 'required|string|max:255',
            'editForm.position_id' => 'required|exists:positions,id',
            'editForm.platform_title' => 'required|string|max:255',
            'photo' => 'nullable|image|max:2048',
        ]);

        try {
            DB::beginTransaction();
            $candidate = Candidate::findOrFail($this->editingCandidateId);

            $cleanPreviousPositions = array_values(array_filter(array_map('trim', (array) $this->editForm['previous_position'])));

            $candidateData = [
                'party_name' => $this->editForm['party_name'],
                'position_id' => $this->editForm['position_id'],
                'achievements' => $this->editForm['achievements'],
                'previous_position' => $cleanPreviousPositions,
                'average_grade' => $this->editForm['average_grade'],
                'status' => 'approved',
                'approved_at' => now(),
            ];

            if ($this->photo) {
                $path = $this->photo->store('candidates-pictures', 'public');
                $candidateData['photo'] = $path;
            }

            $candidate->update($candidateData);

            $candidate->student->update([
                'first_name' => $this->editForm['first_name'],
                'last_name' => $this->editForm['last_name'],
            ]);

            $agendaArray = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $this->editForm['agenda'])))));

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
                'text' => 'Candidate profile saved.',
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
            if (!$activeCycle) {
                return $this->dispatch('swal', [
                    'title' => 'Import Disabled',
                    'text' => 'There is no active election cycle in the system.',
                    'icon' => 'warning',
                ]);
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
                            'election_cycle_id' => $activeCycle->id,
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
                            'election_cycle_id' => $activeCycle->id,
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
            DB::commit();
            $this->reset('csvFile');
            $this->dispatch('swal', [
                'title' => 'Success',
                'text' => "Imported $importedCount candidates.",
                'icon' => 'success',
            ]);
        } catch (\Exception $e) {
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
        return redirect()->route('login');
    }
}; ?>
<div>
    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div class="topbar-info">
                <h2 class="fw-bold text-primary">Manage <span class="text-accent">Candidates Profile</span></h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Unified candidate records and electoral profiles
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
                    <i class="bi bi-file-earmark-arrow-up"></i>
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
                <div class="col-12 col-md-4">
                    <div class="search-wrap-modern">
                        <i class="bi bi-search search-icon"></i>
                        <input type="text" wire:model.live.debounce.300ms="search" class="search-input"
                            placeholder="Search name or ID...">
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <select wire:model.live="departmentFilter" class="form-select-modern w-100">
                        <option value="All Departments">All Departments</option>
                        @foreach ($availableDepartments as $dept)
                            <option value="{{ $dept }}">{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <select wire:model.live="positionFilter" class="form-select-modern w-100">
                        <option value="All Positions">All Positions</option>
                        @foreach ($availablePositions as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
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
                                            <div class="text-primary fw-bold text-nowrap">
                                                {{ $candidate->student->first_name }}
                                                {{ $candidate->student->last_name }}</div>
                                            <div class="small text-muted fw-bold">{{ $candidate->student->student_id }}
                                                | {{ $candidate->student->course }}</div>
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
                                            <i class="bi bi-pencil-square" style="font-size: 1.1rem;"></i>
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
                                            <i class="bi bi-trash" style="font-size: 1.1rem;"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted fst-italic">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="d-md-none">
                @forelse($candidates as $candidate)
                    <div class="p-3 border-bottom position-relative">
                        <div class="d-flex align-items-start gap-3">
                            <div class="d-flex align-items-center justify-content-center fw-bold text-white shadow-sm flex-shrink-0"
                                style="background: {{ $this->getAvatarColor($candidate->id) }}; width: 45px; height: 45px; border-radius: 12px; overflow: hidden;">
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
                                    style="background: rgba(13, 110, 253, 0.1); color: #0d6efd; border: none; width: 40px; height: 40px; border-radius: 10px;"
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
                                    style="background: rgba(220, 53, 69, 0.1); color: #dc3545; border: none; width: 40px; height: 40px; border-radius: 10px;"
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

            <div class="p-3 border-top bg-light">{{ $candidates->links() }}</div>
        </div>
    </main>

    <div class="modal fade" id="editCandidateModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-centered" style="max-width: 95%; width: 800px;">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                <div class="modal-header bg-primary text-white p-3 p-md-4">
                    <h5 class="modal-title fw-bold fs-6 fs-md-5">
                        <i class="bi bi-person-badge-fill me-2"></i>Edit Candidate Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form wire:submit="updateCandidate">
                    <div class="modal-body p-0">
                        <ul class="nav nav-tabs px-3 pt-2 bg-light flex-nowrap overflow-auto border-0" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link active fw-bold small py-2 px-3" data-bs-toggle="tab"
                                    data-bs-target="#tab-basic" type="button">BASIC INFO</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link fw-bold small py-2 px-3" data-bs-toggle="tab"
                                    data-bs-target="#tab-vision" type="button">PLATFORM</button>
                            </li>
                        </ul>

                        <div class="tab-content p-3 p-md-4">
                            <div class="tab-pane fade show active" id="tab-basic">
                                <div class="row g-2 g-md-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted mb-1">CANDIDATE PHOTO</label>
                                        @if ($photo || (isset($editForm['existing_photo']) && $editForm['existing_photo']))
                                            <div class="mt-2 mb-2">
                                                <img src="{{ $photo ? $photo->temporaryUrl() : asset('storage/' . $editForm['existing_photo']) }}"
                                                    class="rounded border shadow-sm"
                                                    style="width: 50px; height: 50px; object-fit: cover;">
                                            </div>
                                        @endif
                                        <div class="input-group">
                                            <label class="input-group-text bg-white border-end-0" for="photoInput"
                                                style="cursor: pointer;">
                                                <i class="bi bi-image text-primary"></i>
                                            </label>

                                            <input type="text" readonly
                                                class="form-control bg-white border-start-0 small"
                                                placeholder="{{ $photo ? $photo->getClientOriginalName() : (isset($editForm['existing_photo']) ? basename($editForm['existing_photo']) : 'No file selected') }}"
                                                style="cursor: default;">

                                            <button class="btn btn-outline-primary btn-sm px-3" type="button"
                                                @click="$refs.photoInput.click()">
                                                Browse
                                            </button>
                                        </div>
                                        <input type="file" x-ref="photoInput" wire:model="photo" class="d-none"
                                            id="photoInput" accept="image/*">

                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">FIRST NAME</label>
                                        <input type="text" wire:model="editForm.first_name"
                                            class="form-control-modern">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">LAST NAME</label>
                                        <input type="text" wire:model="editForm.last_name"
                                            class="form-control-modern">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">PARTY NAME</label>
                                        <input type="text" wire:model="editForm.party_name"
                                            class="form-control-modern">
                                    </div>
                                    <div class="col-12 col-md-6">
                                        <label class="form-label small fw-bold text-muted mb-1">AVG GRADE (GWA)</label>
                                        <input type="text" wire:model="editForm.average_grade"
                                            class="form-control-modern">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted mb-1">ACHIEVEMENTS</label>
                                        <textarea wire:model="editForm.achievements" class="form-control-modern" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="tab-vision">
                                <div class="row g-2 g-md-3">
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted mb-1">PLATFORM
                                            TITLE</label>
                                        <input type="text" wire:model="editForm.platform_title"
                                            class="form-control-modern">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted mb-1">CAMPAIGN
                                            TAGLINE</label>
                                        <input type="text" wire:model="editForm.tagline"
                                            class="form-control-modern fst-italic">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small fw-bold text-muted mb-1">AGENDA</label>
                                        <textarea wire:model="editForm.agenda" class="form-control-modern" rows="6"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-0 p-4 pt-2 bg-white justify-content-between align-items-center"
                        style="border-bottom-left-radius: 15px; border-bottom-right-radius: 15px;">

                        <button type="button"
                            class="btn btn-secondary d-flex align-items-center justify-content-center fw-semibold px-4"
                            data-bs-dismiss="modal"
                            style="border-radius: 10px; font-size: 0.85rem; height: 45px; border: none;">
                            Cancel
                        </button>

                        <button type="submit" class="btn-glow d-flex align-items-center justify-content-center px-4"
                            style="border-radius: 10px; font-size: 0.85rem; height: 45px; font-weight: 600; min-width: 160px;">
                            <span wire:loading wire:target="updateCandidate"
                                class="spinner-border spinner-border-sm me-2"></span>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

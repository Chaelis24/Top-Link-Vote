<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\{WithFileUploads, WithPagination};
use Illuminate\Support\Facades\{Auth, Session, DB};
use App\Models\{Student, User, Candidate, Position, ElectionCycle, Platform};

new #[Layout('layouts.app')] #[Title('Manage Candidates')] class extends Component {
    use WithFileUploads, WithPagination;

    public $csvFile;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $positionFilter = 'All Positions';

    #[Url(history: true)]
    public string $departmentFilter = 'All Departments';

    public $editingCandidateId;
    public $editForm = [
        'first_name' => '',
        'last_name' => '',
        'party_name' => '',
        'position_id' => '',
    ];

    public function with(): array
    {
        return [
            'candidates' => $this->loadCandidates(),
            'totalCandidates' => Candidate::count(),
            'approvedCount' => Candidate::whereIn('status', ['approved', 'active'])->count(),
            'pendingCount' => Candidate::where('status', 'pending')->count(),
            'availablePositions' => Position::distinct()->pluck('name'),
            'availableDepartments' => Position::whereNotNull('student_department')->distinct()->pluck('student_department'),
        ];
    }

    public function loadCandidates()
    {
        return Candidate::with(['student', 'position'])
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
                $query->whereHas('position', fn($q) => $q->where('student_department', $this->departmentFilter));
            })
            ->latest()
            ->paginate(10);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }
    public function updatingPositionFilter()
    {
        $this->resetPage();
    }
    public function updatingDepartmentFilter()
    {
        $this->resetPage();
    }

    public function editCandidate($id)
    {
        $candidate = Candidate::with('student')->findOrFail($id);
        $this->editingCandidateId = $id;
        $this->editForm = [
            'first_name' => $candidate->student->first_name,
            'last_name' => $candidate->student->last_name,
            'party_name' => $candidate->party_name,
            'position_id' => $candidate->position_id,
        ];
        $this->dispatch('open-edit-modal');
    }

    public function updateCandidate()
    {
        $candidate = Candidate::findOrFail($this->editingCandidateId);
        $candidate->update(['party_name' => $this->editForm['party_name'], 'position_id' => $this->editForm['position_id']]);
        $candidate->student->update(['first_name' => $this->editForm['first_name'], 'last_name' => $this->editForm['last_name']]);
        $this->dispatch('close-edit-modal');
        $this->dispatch('notify', message: 'Candidate updated successfully!', type: 'success');
    }

    public function updatedCsvFile()
    {
        $this->importCandidates();
    }

    public function importCandidates()
    {
        $this->validate(['csvFile' => 'required|file|max:5120|mimes:csv,txt']);

        try {
            $activeCycle = ElectionCycle::where('status', 'active')->first();
            if (!$activeCycle) {
                throw new \Exception('No active election cycle found. Please create an active election cycle first.');
            }

            $path = $this->csvFile->getRealPath();
            $file = fopen($path, 'r');

            // Skip header
            fgetcsv($file);

            DB::beginTransaction();

            $importedCount = 0;
            $skippedCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                if (empty($row) || empty(trim($row[0]))) {
                    continue;
                }

                // FIX: Gawing '23-' ang '2023-' para mag-match sa database mo
                $studentId = str_replace('2023-', '23-', trim($row[0]));
                $positionName = trim($row[1]);
                $partyName = trim($row[2]);
                $vision = trim($row[3] ?? 'General Platform');

                $student = Student::where('student_id', $studentId)->first();

                if ($student) {
                    // 1. Siguraduhin o gumawa ng Position base sa Department ng Student
                    $pos = Position::firstOrCreate(
                        [
                            'name' => $positionName,
                            'election_cycle_id' => $activeCycle->id,
                            'student_department' => $student->course,
                        ],
                        [
                            'slug' => \Illuminate\Support\Str::slug($positionName . '-' . $student->course . '-' . $activeCycle->id),
                        ],
                    );

                    // 2. I-update o Gumawa ng Candidate record
                    $can = Candidate::updateOrCreate(
                        [
                            'student_id' => $student->id,
                            'election_cycle_id' => $activeCycle->id,
                        ],
                        [
                            'user_id' => $student->user_id,
                            'position_id' => $pos->id,
                            'party_name' => $partyName,
                            'course' => $student->course, // Importante ito para sa live tallying
                            'status' => 'approved',
                            'approved_at' => now(),
                        ],
                    );

                    // 3. I-update o Gumawa ng Platform record
                    Platform::updateOrCreate(
                        ['candidate_id' => $can->id],
                        [
                            'title' => 'Official Platform',
                            'vision' => $vision,
                            'status' => 'approved',
                            'approved_at' => now(),
                        ],
                    );

                    $importedCount++;
                } else {
                    $skippedCount++;
                }
            }

            fclose($file);
            DB::commit();

            $this->reset('csvFile');
            $message = "Imported $importedCount candidates successfully.";
            if ($skippedCount > 0) {
                $message .= " (Skipped $skippedCount due to missing Student records)";
            }

            $this->dispatch('notify', message: $message, type: 'success');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('notify', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }

    public function resetFilters()
    {
        $this->reset(['search', 'positionFilter', 'departmentFilter']);
    }
    public function deleteCandidate($id)
    {
        Candidate::findOrFail($id)->delete();
        $this->dispatch('notify', message: 'Deleted.', type: 'success');
    }
}; ?>

<div>
    <style>
        .btn-edit-green {
            background: rgba(25, 135, 84, 0.1);
            border: 1px solid #198754;
            color: #198754;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .btn-edit-green:hover {
            background: #198754;
            color: white;
        }
    </style>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2>Manage <span>Candidates</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Import candidates by department</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div x-data="{ progress: 0 }"
                    x-on:livewire-upload-start="window.dispatchEvent(new CustomEvent('show-upload-progress'))"
                    x-on:livewire-upload-progress="progress = $event.detail.progress; window.dispatchEvent(new CustomEvent('update-upload-progress', {detail: {progress: progress}}))">
                    <input type="file" x-ref="csvInput" wire:model.live="csvFile" class="d-none" accept=".csv">
                    <button class="btn btn-outline-glow btn-sm" @click="$refs.csvInput.click()">
                        <i class="bi bi-file-earmark-arrow-up me-1"></i>Import CSV
                    </button>
                </div>
                <button class="btn btn-glow btn-sm" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Candidate
                </button>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-4">
                <div class="glass-card stat-card">
                    <div class="stat-value text-accent">{{ $totalCandidates }}</div>
                    <div class="stat-label">Total Candidates</div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="glass-card stat-card">
                    <div class="stat-value text-purple">{{ $approvedCount }}</div>
                    <div class="stat-label">Approved / Active</div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="glass-card stat-card">
                    <div class="stat-value text-warning">{{ $pendingCount }}</div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>

        <div class="glass-card p-3 mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" wire:model.live.debounce.300ms="search" class="search-glass"
                            placeholder="Search students...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="departmentFilter" class="form-control-glass w-100">
                        <option value="All Departments">All Departments</option>
                        @foreach ($availableDepartments as $dept)
                            <option value="{{ $dept }}">{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="positionFilter" class="form-control-glass w-100">
                        <option value="All Positions">All Positions</option>
                        @foreach ($availablePositions as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-glow btn-sm w-100" wire:click="resetFilters">Reset Filters</button>
                </div>
            </div>
        </div>

        <div class="glass-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-glass mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Candidate</th>
                            <th>Dept / Course</th>
                            <th>Position</th>
                            <th>Party</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($candidates as $index => $candidate)
                            <tr>
                                <td class="text-white-50">{{ $candidates->firstItem() + $index }}</td>
                                <td>
                                    <div class="fw-semibold text-white">{{ $candidate->student->first_name }}
                                        {{ $candidate->student->last_name }}</div>
                                    <div class="small text-white-50">{{ $candidate->student->student_id }}</div>
                                </td>
                                <td>
                                    @php
                                        $dept = strtoupper($candidate->position->student_department ?? 'GENERAL');
                                        $badgeClass = match ($dept) {
                                            'IT' => 'bg-secondary text-white',
                                            'HRMT' => 'bg-info text-dark',
                                            'HST' => 'bg-warning text-dark',
                                            'ECT' => 'bg-danger text-white',
                                            default => 'bg-primary text-white',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} bg-opacity-75">{{ $dept }}</span>
                                </td>
                                <td>{{ $candidate->position->name }}</td>
                                <td><span
                                        class="badge bg-success bg-opacity-25 text-accent">{{ $candidate->party_name }}</span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        <button class="btn-edit-green"
                                            wire:click="editCandidate({{ $candidate->id }})">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="action-btn action-btn-delete m-0"
                                            wire:click="deleteCandidate({{ $candidate->id }})"
                                            wire:confirm="Are you sure?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-white-50 p-4">No candidates found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $candidates->links() }}</div>
        </div>
    </main>

    <div class="modal fade" id="editCandidateModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-0">
                <div class="modal-header border-bottom border-white-10">
                    <h5 class="modal-title text-white">Edit Candidate</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form wire:submit="updateCandidate">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label text-white-50">First Name</label>
                                <input type="text" wire:model="editForm.first_name"
                                    class="form-control-glass text-white w-100">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-white-50">Last Name</label>
                                <input type="text" wire:model="editForm.last_name"
                                    class="form-control-glass text-white w-100">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-white-50">Party Name</label>
                                <input type="text" wire:model="editForm.party_name"
                                    class="form-control-glass text-white w-100">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-white-50">Position</label>
                                <select wire:model="editForm.position_id" class="form-control-glass text-white w-100">
                                    @foreach (App\Models\Position::all() as $pos)
                                        <option value="{{ $pos->id }}">{{ $pos->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-white-10">
                        <button type="submit" class="btn btn-glow btn-sm">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('open-edit-modal', () => {
            let el = document.getElementById('editCandidateModal');
            let modal = bootstrap.Modal.getOrCreateInstance(el);
            modal.show();
        });
        window.addEventListener('close-edit-modal', () => {
            let el = document.getElementById('editCandidateModal');
            let modal = bootstrap.Modal.getInstance(el);
            if (modal) modal.hide();
        });
    </script>
</div>

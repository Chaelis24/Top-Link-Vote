<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\{Auth, Session, DB, Hash};
use Livewire\Attributes\{Layout, Title, Url};
use App\Models\{User, Student, Role, Vote, ElectionCycle};
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Str;

new #[Layout('layouts.admin')] #[Title('Manage Students')] class extends Component {
    use WithFileUploads, WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $course = 'All Courses';
    #[Url]
    public string $year = 'All Years';
    #[Url]
    public string $status = 'All Status';

    public $csvFile;
    public $pendingRows = [];
    public $selectedStudent;
    public $editingStudentId;
    public $editForm = [
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'suffix' => '',
        'course' => '',
        'year_level' => '',
        'status' => '',
        'phone' => '',
        'address' => '',
        'birthday' => '',
        'gender' => '',
    ];

    #[Computed]
    public function activeCycle()
    {
        return ElectionCycle::where('status', 'active')->latest()->first();
    }

    public function with(): array
    {
        $stats = cache()->remember('students_stats', 120, function () {
            return Student::selectRaw(
                "
            count(*) as total,
            count(case when has_voted = 1 then 1 end) as voted,
            count(case when has_voted = 0 and status = 'active' then 1 end) as not_voted,
            count(case when status in ('inactive', 'suspended') then 1 end) as disabled
        ",
            )->first();
        });

        return [
            'students' => $this->loadStudents(),
            'totalStudents' => $stats->total,
            'votedCount' => $stats->voted,
            'notVotedCount' => $stats->not_voted,
            'disabledCount' => $stats->disabled,
        ];
    }

    public function loadStudents()
    {
        return Student::query()
            ->with(['user'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%')
                        ->orWhere('student_id', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->course !== 'All Courses' && $this->course !== '', fn($q) => $q->where('course', $this->course))
            ->when($this->year !== 'All Years' && $this->year !== '', fn($q) => $q->where('year_level', $this->year))
            ->when($this->status !== 'All Status' && $this->status !== '', function ($q) {
                if ($this->status === 'Voted') {
                    $q->where('has_voted', true);
                } elseif ($this->status === 'Not Voted') {
                    $q->where('has_voted', false)->where('status', 'active');
                } elseif ($this->status === 'Deactivated') {
                    $q->whereIn('status', ['inactive', 'suspended']);
                }
            })
            ->orderBy('student_id', 'asc')
            ->paginate(10);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }
    public function updatingCourse()
    {
        $this->resetPage();
    }
    public function updatingYear()
    {
        $this->resetPage();
    }
    public function updatingStatus()
    {
        $this->resetPage();
    }

    public function updatedCsvFile()
    {
        $this->importCSV();
    }

    public function importCSV()
    {
        $this->validate(['csvFile' => 'required|file|max:5120']);

        try {
            $path = $this->csvFile->getRealPath();
            $file = fopen($path, 'r');
            $header = fgetcsv($file);

            $rowsToImport = [];
            while (($row = fgetcsv($file)) !== false) {
                $rowsToImport[] = [
                    'student_id' => trim($row[0]),
                    'first_name' => trim($row[1]),
                    'middle_name' => trim($row[2] ?? ''),
                    'last_name' => trim($row[3]),
                    'suffix' => trim($row[4] ?? ''),
                    'course' => trim($row[5]),
                    'year_level' => (int) $row[6],
                    'phone' => trim($row[8] ?? ''),
                    'address' => trim($row[9] ?? ''),
                    'birthday' => trim($row[10] ?? ''),
                    'gender' => trim($row[11] ?? ''),
                    'email' => trim($row[13] ?? ''),
                ];
            }
            fclose($file);

            if (empty($rowsToImport)) {
                throw new \Exception('No data found.');
            }

            \App\Jobs\ImportStudentsJob::dispatch($rowsToImport);

            $this->reset('csvFile');

            $this->dispatch('swal', [
                'title' => 'Import In Progress',
                'text' => count($rowsToImport) . ' students are being processed in the background. You can continue working.',
                'icon' => 'info',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'title' => 'File Error',
                'text' => $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    public function viewStudent($id)
    {
        $this->selectedStudent = Student::with(['user', 'latestVote'])->findOrFail($id);
        $this->dispatch('open-modal', id: 'viewStudentModal');
    }

    public function editStudent($id)
    {
        $student = Student::findOrFail($id);
        $this->editingStudentId = $id;
        $this->editForm = [
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_name,
            'last_name' => $student->last_name,
            'suffix' => $student->suffix,
            'course' => $student->course,
            'year_level' => $student->year_level,
            'status' => $student->status,
            'phone' => $student->phone,
            'address' => $student->address,
            'birthday' => $student->birthday ? $student->birthday->format('Y-m-d') : '',
            'gender' => $student->gender,
        ];
        $this->dispatch('open-modal', id: 'editStudentModal');
    }

    public function updateStudent()
    {
        $this->validate([
            'editForm.first_name' => 'required|string|max:255',
            'editForm.middle_name' => 'nullable|string|max:255',
            'editForm.last_name' => 'required|string|max:255',
            'editForm.suffix' => 'nullable|string|max:10',
            'editForm.course' => 'required|string',
            'editForm.year_level' => 'required',
            'editForm.status' => 'required|in:active,inactive,suspended',
            'editForm.phone' => 'nullable|numeric',
            'editForm.birthday' => 'nullable|date',
            'editForm.gender' => 'nullable|in:Male,Female,Other',
        ]);

        try {
            $student = Student::findOrFail($this->editingStudentId);
            $student->update([
                'first_name' => $this->editForm['first_name'],
                'last_name' => $this->editForm['last_name'],
                'course' => $this->editForm['course'],
                'year_level' => $this->editForm['year_level'],
                'status' => $this->editForm['status'],
                'phone' => $this->editForm['phone'],
                'address' => $this->editForm['address'],
                'birthday' => $this->editForm['birthday'],
                'gender' => $this->editForm['gender'],
            ]);

            $this->dispatch('close-modal', id: 'editStudentModal');
            $this->dispatch('swal', [
                'title' => 'Success!',
                'text' => 'Student record has been updated successfully.',
                'icon' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'title' => 'Update Failed',
                'text' => 'An error occurred: ' . $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    public function deleteStudent($id)
    {
        try {
            $student = Student::findOrFail($id);
            $student->update(['status' => 'inactive']);

            $this->dispatch('swal', [
                'title' => 'Student Deactivated',
                'text' => 'The student has been set to inactive.',
                'icon' => 'warning',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'title' => 'Error',
                'text' => 'Could not delete record: ' . $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    public function exportStudents()
    {
        $students = Student::with('user')->get();
        $fileName = 'students_export.csv';
        $headers = ['Content-type' => 'text/csv', 'Content-Disposition' => "attachment; filename=$fileName"];
        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Name', 'Course', 'Year', 'Status']);
            foreach ($students as $s) {
                fputcsv($file, [$s->student_id, $s->first_name . ' ' . $s->last_name, $s->course, $s->year_level, $s->status]);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function logout()
    {
        Auth::logout();
        Session::invalidate();
        Session::regenerateToken();
        return redirect()->route('admin.login');
    }
}; ?>

<div wire:poll.15s>
    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div class="topbar-info">
                <h2 class="fw-bold text-primary">Manage <span class="text-accent">Students</span></h2>
                <p class="text-muted mb-0 small">Import & Update Student Profile</p>
            </div>
            <div x-data="{ progress: 0 }" x-on:livewire-upload-progress="progress = $event.detail.progress">
                <input type="file" x-ref="csvInput" wire:model.live="csvFile" class="d-none" accept=".csv">
                <button type="button" class="btn-glow btn-sm d-flex align-items-center justify-content-center"
                    @click="$refs.csvInput.click()" style="height: 38px; min-width: 38px; border-radius: 8px;"
                    title="Import CSV">
                    <i class="bi bi-upload"></i>
                    <span class="hidden md:block ms-1">Import CSV</span>
                </button>
                <input type="file" x-ref="csvInput" class="hidden" wire:model="csvFile" accept=".csv">
            </div>
        </div>

        <div class="row g-2 g-md-3 mb-3 text-center">
            <div class="col-6 col-lg-3">
                <div class="glass-card p-2 p-md-3 border-0 shadow-sm">
                    <div class="stat-value-sm text-lg md:text-2xl">{{ $totalStudents }}</div>
                    <div class="stat-label text-[10px] md:text-xs uppercase font-semibold">Total Students</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="glass-card p-2 p-md-3 border-0 shadow-sm">
                    <div class="stat-value-sm text-success text-lg md:text-2xl">{{ $votedCount }}</div>
                    <div class="stat-label text-[10px] md:text-xs uppercase font-semibold">Voted</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="glass-card p-2 p-md-3 border-0 shadow-sm">
                    <div class="stat-value-sm text-warning text-lg md:text-2xl">{{ $notVotedCount }}</div>
                    <div class="stat-label text-[10px] md:text-xs uppercase font-semibold">Not Voted</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="glass-card p-2 p-md-3 border-0 shadow-sm">
                    <div class="stat-value-sm text-danger text-lg md:text-2xl">{{ $disabledCount }}</div>
                    <div class="stat-label text-[10px] md:text-xs uppercase font-semibold">Disabled</div>
                </div>
            </div>
        </div>

        <div class="glass-card p-3 mb-3 border-0 shadow-sm bg-white">
            <div class="row g-2 align-items-center">

                <div class="col-8 col-md-4">
                    <div class="search-wrap-modern">
                        <i class="bi bi-search"></i>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search..."
                            class="form-control-sm w-100">
                    </div>
                </div>

                <div class="col-4 col-md-2 order-md-last ms-md-auto">
                    <button type="button" class="btn btn-outline-primary btn-sm w-100 py-2 flex-shrink-0"
                        x-on:click="
                Swal.fire({
                    title: 'Export Data?',
                    text: 'Do you want to download the student list as CSV?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#0d6efd',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, download it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $wire.exportStudents()
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                        Toast.fire({
                            icon: 'success',
                            title: 'Preparing download...'
                        });
                    }
                })
            ">
                        <i class="bi bi-download"></i>
                        <span class="d-none d-md-inline ms-1">Export</span>
                    </button>
                </div>

                <div class="col-4 col-md-2">
                    <select wire:model.live="course" class="form-select-modern py-2 w-100"
                        style="font-size: 0.8rem; padding-left: 4px; padding-right: 4px;">
                        <option value="All Courses">🏢 All Courses</option>
                        <option>IT</option>
                        <option>HRMT</option>
                        <option>HST</option>
                        <option>ECT</option>
                    </select>
                </div>

                <div class="col-4 col-md-2">
                    <select wire:model.live="year" class="form-select-modern py-2 w-100"
                        style="font-size: 0.8rem; padding-left: 4px; padding-right: 4px;">
                        <option value="All Years">🎓 All Years</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                    </select>
                </div>

                <div class="col-4 col-md-2">
                    <select wire:model.live="status" class="form-select-modern py-2 w-100"
                        style="font-size: 0.8rem; padding-left: 4px; padding-right: 4px;">
                        <option value="All Status">📊 All Status</option>
                        <option>Voted</option>
                        <option>Not Voted</option>
                        <option>Deactivated</option>
                    </select>
                </div>

            </div>
        </div>

        <div class="glass-card p-0 overflow-hidden border-0 shadow-sm">
            <div class="table-responsive hidden md:block">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Student Name</th>
                            <th>Course</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th class="text-center pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="align-middle">
                        @forelse($students as $student)
                            <tr wire:key="student-desktop-{{ $student->id }}">
                                <td class="ps-3">{{ $student->student_id }}</td>
                                <td>
                                    {{ $student->first_name }}
                                    {{ $student->middle_name ? substr($student->middle_name, 0, 1) . '.' : '' }}
                                    {{ $student->last_name }}{{ $student->suffix ? ', ' . $student->suffix : '' }}
                                </td>
                                <td>{{ $student->course }} - {{ $student->formatted_year }}</td>
                                <td>{{ $student->user->email ?? 'N/A' }}</td>
                                <td>
                                    @if ($student->has_voted)
                                        <span class="badge-approved py-1 px-2">
                                            <i class="bi bi-check-circle-fill me-1"></i> Voted
                                        </span>
                                    @elseif($student->status === 'active')
                                        <span class="badge-approved py-1 px-2">
                                            <i class="bi bi-person-check-fill me-1"></i> Active
                                        </span>
                                    @elseif($student->status === 'inactive' || $student->status === 'suspended')
                                        <span class="badge-danger-soft py-1 px-2">
                                            <i class="bi bi-slash-circle me-1"></i> Deactivated
                                        </span>
                                    @else
                                        <span class="badge-danger-soft py-1 px-2">
                                            <i class="bi bi-x-circle-fill me-1"></i> Not Voted
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <button wire:click="viewStudent({{ $student->id }})"
                                            class="flex items-center justify-center w-[32px] h-[32px] rounded-lg border-none bg-blue-500/10 text-blue-500 hover:bg-blue-500/20 transition-colors">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button wire:click="editStudent({{ $student->id }})"
                                            class="flex items-center justify-center w-[32px] h-[32px] rounded-lg border-none bg-indigo-500/10 text-indigo-500 hover:bg-indigo-500/20 transition-colors">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button type="button"
                                            x-on:click="
                                                Swal.fire({
                                                    title: 'Deactivate Student?',
                                                    text: 'This will set the student status to inactive.',
                                                    icon: 'warning',
                                                    showCancelButton: true,
                                                    confirmButtonColor: '#f43f5e',
                                                    cancelButtonColor: '#6c757d',
                                                    confirmButtonText: 'Yes, deactivate it!',
                                                    cancelButtonText: 'Cancel'
                                                }).then((result) => {
                                                    if (result.isConfirmed) {
                                                        $wire.deleteStudent({{ $student->id }})
                                                    }
                                                })
                                            "
                                            class="flex items-center justify-center w-[32px] h-[32px] rounded-lg border-none bg-rose-500/10 text-rose-500 hover:bg-rose-500/20 transition-colors">
                                            <i class="bi bi-person-x"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="md:hidden">
                @forelse($students as $student)
                    <div wire:key="student-mobile-{{ $student->id }}" class="p-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="text-xs text-muted fw-bold mb-1">ID: {{ $student->student_id }}</div>
                                <div class="fw-bold text-dark">{{ $student->first_name }} {{ $student->last_name }}
                                </div>
                                <div class="small text-muted">{{ $student->course }} - {{ $student->formatted_year }}
                                </div>
                            </div>
                            <div>=
                                @if ($student->has_voted)
                                    <span class="badge-approved py-1 px-2" style="font-size: 0.7rem;">Voted</span>
                                @elseif($student->status === 'active')
                                    <span class="badge-approved py-1 px-2" style="font-size: 0.7rem;">Active</span>
                                @else
                                    <span class="badge-danger-soft py-1 px-2"
                                        style="font-size: 0.7rem;">Disabled</span>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="small text-muted text-truncate" style="max-width: 150px;">
                                <i class="bi bi-envelope me-1"></i>{{ $student->user->email ?? 'N/A' }}
                            </div>
                            <div class="d-flex gap-2">
                                <button wire:click="viewStudent({{ $student->id }})"
                                    style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: none; width: 32px; height: 32px; border-radius: 6px;">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button wire:click="editStudent({{ $student->id }})"
                                    style="background: rgba(99, 102, 241, 0.1); color: #6366f1; border: none; width: 32px; height: 32px; border-radius: 6px;">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <button wire:click="deleteStudent({{ $student->id }})" wire:confirm="Deactivate?"
                                    style="background: rgba(244, 63, 94, 0.1); color: #f43f5e; border: none; width: 32px; height: 32px; border-radius: 6px;">
                                    <i class="bi bi-person-x"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-5 text-center text-muted small">No records found.</div>
                @endforelse
            </div>

            <div class="p-3 border-top bg-light custom-pagination">
                {{ $students->links() }}
            </div>
        </div>
    </main>

    <div class="modal fade" id="viewStudentModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                <div class="modal-header bg-primary text-white p-3">
                    <h6 class="modal-title fw-bold mb-0">Student Profile Details</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    @if ($selectedStudent)
                        <div class="p-4 text-center border-bottom bg-light">
                            <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white fw-bold shadow-sm mb-3"
                                style="width: 60px; height: 60px; border-radius: 50%; font-size: 1.5rem;">
                                {{ strtoupper(substr($selectedStudent->first_name, 0, 1)) }}{{ strtoupper(substr($selectedStudent->last_name, 0, 1)) }}
                            </div>
                            <h5 class="fw-bold text-dark mb-0">{{ $selectedStudent->first_name }}
                                {{ $selectedStudent->last_name }}</h5>
                            <p class="text-muted small mb-0">{{ $selectedStudent->student_id }} |
                                {{ $selectedStudent->course }}</p>
                        </div>

                        <div class="p-4">
                            <h6 class="text-primary fw-bold small mb-3 text-uppercase" style="letter-spacing: 1px;">
                                Information</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-6">
                                    <label class="text-primary fw-bold d-block mb-0" style="font-size: 0.65rem;">EMAIL
                                        ADDRESS</label>
                                    <span class="text-dark small">{{ $selectedStudent->user->email ?? 'N/A' }}</span>
                                </div>
                                <div class="col-6">
                                    <label class="text-primary fw-bold d-block mb-0"
                                        style="font-size: 0.65rem;">GENDER</label>
                                    <span class="text-dark small">{{ $selectedStudent->gender ?? 'N/A' }}</span>
                                </div>
                            </div>

                            <div class="p-3 rounded-3 border-0 shadow-sm"
                                style="background: #f8f9ff; border-left: 4px solid #0d6efd !important;">
                                <h6 class="text-primary fw-bold small mb-2 text-uppercase"
                                    style="letter-spacing: 1px;">Voting Status</h6>

                                @if ($selectedStudent->latestVote)
                                    <div class="mb-2">
                                        <label class="text-primary fw-bold d-block mb-1"
                                            style="font-size: 0.65rem;">REFERENCE NO.</label>
                                        <span
                                            class="badge bg-white text-primary border border-primary-subtle px-2 py-1 font-monospace"
                                            style="font-size: 0.85rem;">
                                            {{ $selectedStudent->latestVote->reference_number }}
                                        </span>
                                    </div>
                                    <div>
                                        <label class="text-primary fw-bold d-block mb-0"
                                            style="font-size: 0.65rem;">VOTED ON</label>
                                        <span class="text-dark small">
                                            <i class="bi bi-calendar-check me-1"></i>
                                            {{ $selectedStudent->latestVote->created_at->timezone('Asia/Manila')->format('M d, Y - h:i A') }}
                                        </span>
                                    </div>
                                @else
                                    <div class="py-2">
                                        <span class="text-muted small fst-italic">
                                            <i class="bi bi-info-circle me-1"></i> Not yet voted
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer border-0 p-3">
                    <button type="button" class="btn btn-secondary btn-sm fw-bold px-4"
                        data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editStudentModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-lg modal-dialog-centered px-2">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                <div class="modal-header bg-primary text-white p-2 p-md-3">
                    <h6 class="modal-title fw-bold small mb-0">Edit Student Profile</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        style="font-size: 0.75rem;"></button>
                </div>
                <form wire:submit="updateStudent">
                    <div class="modal-body p-3">
                        <div class="row g-2">
                            <div class="col-md-4 col-12">
                                <label class="form-label mb-0 text-primary fw-bold" style="font-size: 0.65rem;">FIRST
                                    NAME</label>
                                <input type="text" wire:model="editForm.first_name"
                                    class="form-control-modern py-1 text-sm bg-light" disabled
                                    style="font-size: 0.8rem;">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label mb-0 text-primary fw-bold" style="font-size: 0.65rem;">MIDDLE
                                    NAME</label>
                                <input type="text" wire:model="editForm.middle_name"
                                    class="form-control-modern py-1 text-sm bg-light" disabled
                                    style="font-size: 0.8rem;">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label mb-0 text-primary fw-bold" style="font-size: 0.65rem;">LAST
                                    NAME</label>
                                <input type="text" wire:model="editForm.last_name"
                                    class="form-control-modern py-1 text-sm bg-light" disabled
                                    style="font-size: 0.8rem;">
                            </div>
                            <div class="col-md-2 col-12">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">SUFFIX</label>
                                <input type="text" wire:model="editForm.suffix"
                                    class="form-control-modern py-1 text-sm bg-light" disabled
                                    style="font-size: 0.8rem;" placeholder="N/A">
                            </div>

                            <div class="col-md-4 col-4">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">COURSE</label>
                                <select wire:model="editForm.course"
                                    class="form-select-modern py-1 @error('editForm.course') is-invalid @enderror"
                                    style="font-size: 0.8rem;">
                                    <option value="">Select</option>
                                    <option>IT</option>
                                    <option>HRMT</option>
                                    <option>HST</option>
                                    <option>ECT</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-4">
                                <label class="form-label mb-0 text-primary fw-bold" style="font-size: 0.65rem;">YEAR
                                    LEVEL</label>
                                <select wire:model="editForm.year_level" class="form-select-modern py-1"
                                    style="font-size: 0.8rem;">
                                    <option value="1">1st Yr</option>
                                    <option value="2">2nd Yr</option>
                                    <option value="3">3rd Yr</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-4">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">STATUS</label>
                                <select wire:model="editForm.status" class="form-select-modern py-1"
                                    style="font-size: 0.8rem;">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>

                            <div class="col-md-5 col-12">
                                <label class="form-label mb-0 text-primary fw-bold" style="font-size: 0.65rem;">PHONE
                                    NUMBER</label>
                                <input type="text" wire:model="editForm.phone"
                                    class="form-control-modern py-1 @error('editForm.phone') is-invalid @enderror"
                                    placeholder="09xxxxxxxxx" style="font-size: 0.8rem;">
                            </div>
                            <div class="col-md-3 col-6">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">GENDER</label>
                                <select wire:model="editForm.gender" class="form-select-modern py-1 bg-light"
                                    style="font-size: 0.8rem;" disabled>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-4 col-6">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">BIRTHDAY</label>
                                <input type="date" wire:model="editForm.birthday"
                                    class="form-control-modern py-1 bg-light text-sm" style="font-size: 0.8rem;"
                                    disabled>
                            </div>

                            <div class="col-12">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">ADDRESS</label>
                                <textarea wire:model="editForm.address"
                                    class="form-control-modern py-1 @error('editForm.address') is-invalid @enderror" rows="1"
                                    style="font-size: 0.8rem;"></textarea>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer bg-light p-2">
                        <button type="submit" class="btn-glow px-4 py-2 fw-bold"
                            style="font-size: 0.7rem; border-radius: 5px;" wire:loading.attr="disabled">
                            <span wire:loading.remove>Update Profile</span>
                            <span wire:loading><span class="spinner-border spinner-border-sm"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

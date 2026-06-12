<?php

use Illuminate\Support\Str;
use Livewire\Volt\Component;
use App\Traits\AuthenticatesLogout;
use App\Http\Requests\Admin\StudentRequest;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\{WithFileUploads, WithPagination};
use Illuminate\Support\Facades\{Auth, Session, DB, Hash};
use App\Models\{User, Student, Role, Vote, ElectionCycle, Block, Course};

new #[Layout('layouts.admin')] #[Title('Manage Students')] class extends Component {
    use WithFileUploads, WithPagination, AuthenticatesLogout;

    #[Url]
    public string $search = '';
    #[Url]
    public string $course = 'All Courses';
    #[Url]
    public string $block_id = 'All Blocks';
    #[Url]
    public string $year = 'All Years';
    #[Url]
    public string $status = 'All Status';

    public $selectedStudents = [];
    public $selectAll = false;
    public $csvFile;
    public $pendingRows = [];
    public $selectedStudent;
    public $editingStudentId;
    public $editForm = [
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'suffix' => '',
        'block_id' => '',
        'status' => '',
        'phone' => '',
        'address' => '',
        'birthday' => '',
        'gender' => '',
    ];

    #[Computed]
    public function activeCycle()
    {
        return ElectionCycle::getActiveCycle();
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

    public function loadStudents($paginate = true)
    {
        $query = Student::query()
            ->with(['user', 'block.course'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%')
                        ->orWhere('student_id', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->course !== 'All Courses' && $this->course !== '', function ($q) {
                $q->whereHas('block.course', fn($sub) => $sub->where('name', $this->course));
            })
            ->when($this->block_id !== 'All Blocks' && $this->block_id !== '', function ($q) {
                $q->where('block_id', $this->block_id);
            })
            ->when($this->year !== 'All Years' && $this->year !== '', function ($q) {
                $q->whereHas('block', fn($sub) => $sub->where('year_level', $this->year));
            })
            ->when($this->status !== 'All Status' && $this->status !== '', function ($q) {
                if ($this->status === 'Voted') {
                    $q->where('has_voted', true);
                } elseif ($this->status === 'Not Voted') {
                    $q->where('has_voted', false)->where('status', 'active');
                } elseif ($this->status === 'Deactivated') {
                    $q->whereIn('status', ['inactive', 'suspended']);
                }
            })
            ->orderBy('student_id', 'asc');

        return $paginate ? $query->paginate(10) : $query->get();
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedStudents = $this->loadStudents(false)->pluck('id')->toArray();
        } else {
            $this->selectedStudents = [];
        }
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

    public function viewStudent($id)
    {
        try {
            $this->selectedStudent = Student::with(['user', 'latestVote'])->findOrFail($id);
            $this->dispatch('open-modal', id: 'viewStudentModal');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->dispatch('swal', [
                'title' => 'Error',
                'text' => 'Student not found.',
                'icon' => 'error',
            ]);
        }
    }

    public function importCSV()
    {
        $this->validate(StudentRequest::importRules());

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
                    'section' => trim($row[7] ?? ''),
                    'status' => trim($row[8] ?? ''),
                    'phone' => trim($row[9] ?? ''),
                    'address' => trim($row[10] ?? ''),
                    'birthday' => trim($row[11] ?? ''),
                    'gender' => trim($row[12] ?? ''),
                    'user_id' => trim($row[13] ?? ''),
                    'email' => trim($row[14] ?? ''),
                    'role' => trim($row[15] ?? ''),
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

    public function editStudent($id)
    {
        $student = Student::with('block')->findOrFail($id);
        $this->editingStudentId = $id;

        $this->editForm = [
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_name,
            'last_name' => $student->last_name,
            'suffix' => $student->suffix,
            'block_id' => $student->block_id,
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
        $this->validate(StudentRequest::updateRules());

        try {
            $student = Student::findOrFail($this->editingStudentId);
            $student->update($this->editForm);

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

    public function bulkDeactivate()
    {
        if (empty($this->selectedStudents)) {
            return;
        }

        $count = count($this->selectedStudents);
        Student::whereIn('id', $this->selectedStudents)->update(['status' => 'inactive']);

        $this->selectedStudents = [];
        $this->selectAll = false;

        $this->dispatch('swal', [
            'title' => 'Success!',
            'text' => 'Successfully deactivated ' . $count . ' students.',
            'icon' => 'success',
        ]);
    }

    public function exportStudents()
    {
        $students = Student::with('block.course')->get();
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, ['ID', 'Name', 'Course', 'Year', 'Status']);
        foreach ($students as $s) {
            fputcsv($csv, [$s->student_id, $s->first_name . ' ' . $s->last_name, $s->block->course->name ?? 'N/A', $s->block->year_level ?? 'N/A', $s->status]);
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);
        $tempPath = tempnam(sys_get_temp_dir(), 'students_') . '.csv';
        file_put_contents($tempPath, $content);

        return response()->download($tempPath, 'students_export.csv')->deleteFileAfterSend(true);
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
                <h2 class="fw-bold text-primary">Manage <span class="text-accent">Students</span></h2>
                <p class="text-muted mb-0 small">Import & Update Student Profile</p>
            </div>
            <div x-data="{
                confirmImport(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    Swal.fire({
                        title: 'Import Students?',
                        text: `Process student data from '${file.name}'?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#1e3a8a',
                        confirmButtonText: 'Yes, import!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            @this.upload('csvFile', file, () => { @this.importCSV(); });
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
                    <div class="stat-label text-[10px] md:text-xs uppercase font-semibold">Deactivated</div>
                </div>
            </div>
        </div>

        <div class="glass-card p-3 mb-3 border-0 shadow-sm bg-white">
            <div class="row g-2 align-items-center">

                <div class="col-4 col-md-2">
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
                    <select wire:model.live="course" class="form-select-modern py-2 w-100">
                        <option value="All Courses">🏢 All Courses</option>
                        @foreach (\App\Models\Course::all() as $c)
                            <option value="{{ $c->name }}">🏢 {{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-4 col-md-2">
                    <select wire:model.live="block_id" class="form-select-modern py-2 w-100">
                        <option value="All Blocks">🏫 All Blocks</option>
                        @foreach (\App\Models\Block::all() as $b)
                            <option value="{{ $b->id }}">🏫{{ $b->year_level }} - {{ $b->section }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-4 col-md-2">
                    <select wire:model.live="year" class="form-select-modern py-2 w-100">
                        <option value="All Years">🎓 All Years</option>
                        @foreach (\App\Models\Block::distinct()->pluck('year_level')->sort() as $y)
                            <option value="{{ $y }}">🎓{{ $y }}st/nd/rd Year</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-4 col-md-2">
                    <select wire:model.live="status" class="form-select-modern py-2 w-100"
                        style="font-size: 0.8rem; padding-left: 4px; padding-right: 4px;">
                        <option value="All Status">📊 All Status</option>
                        <option>📊 Voted</option>
                        <option>📊 Not Voted</option>
                        <option>📊 Deactivated</option>
                    </select>
                </div>

            </div>
        </div>

        <div class="glass-card p-0 overflow-hidden border-0 shadow-sm">
            <div class="table-responsive hidden md:block">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr class="align-middle">
                            <th class="p-3 ps-4" style="width: 50px;">
                                <div class="form-check">
                                    <input type="checkbox" wire:model.live="selectAll" class="form-check-input"
                                        id="selectAll">
                                </div>
                            </th>
                            <th class="p-3">ID</th>
                            <th class="p-3">Student Name</th>
                            <th class="p-3">Course</th>
                            <th class="p-3">Email</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="align-middle">
                        @forelse($students as $student)
                            <tr wire:key="student-desktop-{{ $student->id }}">
                                <td class="ps-4">
                                    <input type="checkbox" value="{{ $student->id }}"
                                        wire:model.live="selectedStudents" class="form-check-input">
                                </td>
                                <td>{{ $student->student_id }}</td>
                                <td class="text-primary fw-bold">
                                    {{ $student->first_name }}
                                    {{ $student->middle_name ? substr($student->middle_name, 0, 1) . '.' : '' }}
                                    {{ $student->last_name }}{{ $student->suffix ? ', ' . $student->suffix : '' }}
                                </td>
                                <td>
                                    {{ $student->block->course->name ?? 'N/A' }} -
                                    {{ $student->block->year_level ?? 'N/A' }}{{ $student->block->section ?? '' }}
                                </td>
                                <td>{{ $student->user->email ?? 'N/A' }}</td>
                                <td>
                                    @if ($student->has_voted)
                                        <span class="badge-approved py-1 px-2"><i
                                                class="bi bi-check-circle-fill me-1"></i> Voted</span>
                                    @elseif($student->status === 'active')
                                        <span class="badge-approved py-1 px-2"><i
                                                class="bi bi-person-check-fill me-1"></i> Active</span>
                                    @elseif($student->status === 'inactive' || $student->status === 'suspended')
                                        <span class="badge-danger-soft py-1 px-2"><i
                                                class="bi bi-slash-circle me-1"></i> Deactivated</span>
                                    @else
                                        <span class="badge-danger-soft py-1 px-2"><i
                                                class="bi bi-x-circle-fill me-1"></i> Not Voted</span>
                                    @endif
                                </td>
                                <td class="text-center pe-4">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <x-icon-button variant="custom" wire:click="viewStudent({{ $student->id }})"
                                            class="flex items-center justify-center border-none bg-blue-500/10 text-blue-500 hover:bg-blue-500/20 transition-colors">
                                            <i class="bi bi-eye"></i>
                                        </x-icon-button>
                                        <x-icon-button variant="custom" wire:click="editStudent({{ $student->id }})"
                                            class="flex items-center justify-center border-none bg-indigo-500/10 text-indigo-500 hover:bg-indigo-500/20 transition-colors">
                                            <i class="bi bi-pencil-square"></i>
                                        </x-icon-button>
                                        <x-icon-button variant="custom" type="button"
                                            class="flex items-center justify-center border-none bg-rose-500/10 text-rose-500 hover:bg-rose-500/20 transition-colors"
                                            x-data
                                            @click.prevent.stop="Swal.fire({
                                                title: 'Deactivate Student?',
                                                text: 'This will set the status of {{ count($selectedStudents) }} student(s) to inactive.',
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
                                            })">
                                            <i class="bi bi-person-x"></i>
                                        </x-icon-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="md:hidden mb-3">
                @forelse($students as $student)
                    <div wire:key="student-mobile-{{ $student->id }}"
                        class="p-3 border-bottom {{ $loop->index >= 6 ? 'd-none' : '' }}">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="text-xs text-muted fw-bold mb-1">ID: {{ $student->student_id }}</div>
                                <div class="fw-bold text-dark">{{ $student->first_name }} {{ $student->last_name }}
                                </div>
                                <div class="small text-muted">{{ $student->block->course->name ?? 'N/A' }} -
                                    {{ $student->block->year_level ?? 'N/A' }}</div>
                            </div>
                            <div>
                                @if ($student->has_voted)
                                    <span class="badge-approved py-1 px-2 fs-6"
                                        style="font-size: 0.7rem;">Voted</span>
                                @elseif($student->status === 'active')
                                    <span class="badge-approved py-1 px-2 fs-6"
                                        style="font-size: 0.7rem;">Active</span>
                                @else
                                    <span class="badge-danger-soft py-1 px-2 fs-6"
                                        style="font-size: 0.7rem;">Disabled</span>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div class="small text-muted text-truncate" style="max-width: 250px;">
                                <i class="bi bi-envelope me-1"></i>{{ $student->user->email ?? 'N/A' }}
                            </div>
                            <div class="d-flex gap-2">
                                <x-icon-button variant="custom" wire:click="viewStudent({{ $student->id }})"
                                    style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: none; width: 32px; height: 32px; border-radius: 6px;">
                                    <i class="bi bi-eye"></i>
                                </x-icon-button>
                                <x-icon-button variant="custom" wire:click="editStudent({{ $student->id }})"
                                    style="background: rgba(99, 102, 241, 0.1); color: #6366f1; border: none; width: 32px; height: 32px; border-radius: 6px;">
                                    <i class="bi bi-pencil-square"></i>
                                </x-icon-button>
                                <x-icon-button variant="custom"
                                    style="background: rgba(244, 63, 94, 0.1); color: #f43f5e; border: none; width: 32px; height: 32px; border-radius: 6px;"
                                    x-data
                                    @click="
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
                                    ">
                                    <i class="bi bi-person-x"></i>
                                </x-icon-button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-5 text-center text-muted small">No records found.</div>
                @endforelse
            </div>

            <div class="custom-pagination">
                {{ $students->links('layouts.partials.custom-pagination') }}
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
                                {{ $selectedStudent->course->name ?? 'N/A' }}-{{ $selectedStudent->block->year_level ?? 'N/A' }}{{ $selectedStudent->block->section ?? 'N/A' }}
                            </p>
                        </div>

                        <div class="p-3">
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
                                            {{ $selectedStudent->latestVote->created_at?->timezone('Asia/Manila')->format('M d, Y - h:i A') }}
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
                    <x-button variant="gray" type="button" class="btn btn-secondary btn-sm fw-bold px-3"
                        data-bs-dismiss="modal">Close
                    </x-button>
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
                            <div class="col-md-4 col-4">
                                <label class="form-label mb-0 text-primary fw-bold" style="font-size: 0.65rem;">FIRST
                                    NAME</label>
                                <input type="text" wire:model="editForm.first_name"
                                    class="form-control-modern py-1 text-sm bg-light" disabled
                                    style="font-size: 0.8rem;">
                            </div>
                            <div class="col-md-3 col-4">
                                <label class="form-label mb-0 text-primary fw-bold" style="font-size: 0.65rem;">MIDDLE
                                    NAME</label>
                                <input type="text" wire:model="editForm.middle_name"
                                    class="form-control-modern py-1 text-sm bg-light" disabled
                                    style="font-size: 0.8rem;">
                            </div>
                            <div class="col-md-3 col-4">
                                <label class="form-label mb-0 text-primary fw-bold" style="font-size: 0.65rem;">LAST
                                    NAME</label>
                                <input type="text" wire:model="editForm.last_name"
                                    class="form-control-modern py-1 text-sm bg-light" disabled
                                    style="font-size: 0.8rem;">
                            </div>
                            <div class="col-md-2 col-4">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">SUFFIX</label>
                                <input type="text" wire:model="editForm.suffix"
                                    class="form-control-modern py-1 text-sm bg-light" disabled
                                    style="font-size: 0.8rem;" placeholder="N/A">
                            </div>

                            <div class="col-md-3 col-4">
                                <label class="form-label mb-0 text-primary fw-bold" style="font-size: 0.65rem;">COURSE
                                    & BLOCK</label>
                                <select wire:model="editForm.block_id" class="form-select-modern py-1"
                                    style="font-size: 0.8rem;" disabled>
                                    <option value="">Select Course & Block</option>
                                    @foreach (Block::with('course')->get() as $block)
                                        <option value="{{ $block->id }}">
                                            {{ $block->course->name ?? 'N/A' }} -
                                            {{ $block->year_level }} {{ $block->section }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 col-4">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">STATUS</label>
                                <select wire:model="editForm.status" class="form-select-modern py-1"
                                    style="font-size: 0.8rem;">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>

                            <div class="col-md-3 col-4">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">GENDER</label>
                                <select wire:model="editForm.gender" class="form-select-modern py-1 bg-light"
                                    style="font-size: 0.8rem;" disabled>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div class="col-md-3 col-4">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">BIRTHDAY</label>
                                <input type="date" wire:model="editForm.birthday"
                                    class="form-control-modern py-1 bg-light text-sm" style="font-size: 0.8rem;"
                                    disabled>
                            </div>
                            <div class="col-md-5 col-4">
                                <label class="form-label mb-0 text-primary fw-bold" style="font-size: 0.65rem;">PHONE
                                    NUMBER</label>
                                <input type="text" wire:model="editForm.phone"
                                    class="form-control-modern py-1 @error('editForm.phone') is-invalid @enderror"
                                    placeholder="09xxxxxxxxx" style="font-size: 0.8rem;">
                            </div>
                            <div class="col-md-7 col-12">
                                <label class="form-label mb-0 text-primary fw-bold"
                                    style="font-size: 0.65rem;">ADDRESS</label>
                                <textarea wire:model="editForm.address"
                                    class="form-control-modern py-1 @error('editForm.address') is-invalid @enderror" rows="1"
                                    style="font-size: 0.8rem;"></textarea>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer bg-light p-2">
                        <x-button type="submit" variant="glow" style="font-size: 0.7rem; border-radius: 5px;"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>Update Profile</span>
                            <span wire:loading><span class="spinner-border spinner-border-sm"></span></span>
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

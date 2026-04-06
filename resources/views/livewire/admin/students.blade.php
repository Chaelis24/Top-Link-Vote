<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Facades\{Auth, Session, DB, Hash};
use Illuminate\Support\Str;
use App\Models\{User, Student, Role};

new #[Layout('layouts.app')] #[Title('Manage Students')] class extends Component {
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

    public function with(): array
    {
        return [
            'students' => $this->loadStudents(),
            'totalStudents' => Student::count(),
            'votedCount' => Student::where('has_voted', true)->count(),
            'notVotedCount' => Student::where('has_voted', false)->where('status', 'active')->count(),
            'disabledCount' => Student::whereIn('status', ['inactive', 'suspended'])->count(),
        ];
    }

    public function loadStudents()
    {
        return Student::with('user')
            ->where('student_id', '!=', '001')
            ->when($this->search, function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery
                        ->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%')
                        ->orWhere('student_id', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->course !== 'All Courses', fn($q) => $q->where('course', $this->course))
            ->when($this->year !== 'All Years', fn($q) => $q->where('year_level', $this->year))
            ->when($this->status !== 'All Status', function ($q) {
                if ($this->status === 'Voted') {
                    $q->where('has_voted', true);
                } elseif ($this->status === 'Not Voted') {
                    $q->where('has_voted', false)->where('status', 'active');
                } elseif ($this->status === 'Disabled') {
                    $q->whereIn('status', ['inactive', 'suspended']);
                }
            })
            ->latest()
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
            $data = array_map('str_getcsv', file($path));

            if (count($data) <= 1) {
                throw new \Exception('The CSV file is empty or only contains headers.');
            }

            array_shift($data);

            $errors = [];
            $rowsToImport = [];
            $existingIds = Student::pluck('student_id')->toArray();
            $existingEmails = User::pluck('email')->toArray();
            $seenIdsInCsv = [];

            foreach ($data as $index => $row) {
                $rowNum = $index + 2;

                $studentId = isset($row[0]) ? trim($row[0]) : '';
                $firstName = isset($row[1]) ? trim($row[1]) : '';
                $lastName = isset($row[3]) ? trim($row[3]) : '';
                $course = isset($row[5]) ? trim($row[5]) : '';
                $yearLevel = isset($row[6]) ? trim($row[6]) : '';
                $phone = isset($row[8]) ? trim($row[8]) : '';
                $address = isset($row[9]) ? trim($row[9]) : '';
                $birthday = isset($row[10]) ? trim($row[10]) : '';
                $gender = isset($row[11]) ? trim($row[11]) : '';

                $email = '';
                foreach ($row as $val) {
                    if (filter_var(trim($val), FILTER_VALIDATE_EMAIL)) {
                        $email = trim($val);
                        break;
                    }
                }

                if (empty($studentId) || empty($firstName) || empty($lastName) || empty($course) || empty($yearLevel) || empty($phone) || empty($address) || empty($birthday) || empty($gender)) {
                    $errors[] = "Row $rowNum: Missing required columns.";
                }

                if (empty($email)) {
                    $errors[] = "Row $rowNum: No valid email address found.";
                } elseif (in_array($email, $existingEmails)) {
                    $errors[] = "Row $rowNum: Email ($email) is already registered.";
                }

                if (in_array($studentId, $existingIds)) {
                    $errors[] = "Row $rowNum: Student ID ($studentId) already exists in database.";
                }

                if (in_array($studentId, $seenIdsInCsv)) {
                    $errors[] = "Row $rowNum: Duplicate Student ID ($studentId).";
                }
                $seenIdsInCsv[] = $studentId;

                if (empty($errors)) {
                    $rowsToImport[] = [
                        'student_id' => $studentId,
                        'first_name' => $firstName,
                        'middle_name' => isset($row[2]) ? trim($row[2]) : null,
                        'last_name' => $lastName,
                        'suffix' => isset($row[4]) ? trim($row[4]) : null,
                        'course' => $course,
                        'year_level' => (int) $yearLevel,
                        'phone' => $phone,
                        'address' => $address,
                        'birthday' => $birthday,
                        'gender' => $gender,
                        'email' => $email,
                    ];
                }
            }

            if (!empty($errors)) {
                $errorMessage = implode(' | ', array_slice($errors, 0, 10));
                if (count($errors) > 10) {
                    $errorMessage .= ' ...and more errors.';
                }
                throw new \Exception($errorMessage);
            }

            $this->pendingRows = $rowsToImport;
            $this->dispatch('confirm-csv-import', count: count($rowsToImport));
        } catch (\Exception $e) {
            $this->reset('csvFile');
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    public function processImport()
    {
        if (empty($this->pendingRows)) {
            return;
        }

        try {
            DB::beginTransaction();
            $studentRole = Role::where('name', 'student')->first();

            foreach ($this->pendingRows as $item) {
                $user = User::create([
                    'name' => trim($item['first_name'] . ' ' . $item['last_name']),
                    'email' => $item['email'],
                    'password' => Hash::make(Str::random(10)),
                ]);

                if ($studentRole) {
                    $user->roles()->attach($studentRole->id);
                }

                $user->student()->create(
                    array_merge($item, [
                        'status' => 'active',
                        'has_voted' => false,
                    ]),
                );
            }

            DB::commit();
            $count = count($this->pendingRows);
            $this->reset(['csvFile', 'pendingRows']);
            $this->dispatch('notify', message: $count . ' students imported successfully!', type: 'success');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->dispatch('notify', message: $e->getMessage(), type: 'error');
        }
    }

    public function exportStudents()
    {
        $students = Student::with('user')->get();
        $fileName = 'students_' . now()->format('Ymd') . '.csv';
        $headers = ['Content-type' => 'text/csv', 'Content-Disposition' => "attachment; filename=$fileName"];
        $callback = function () use ($students) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'First', 'Last', 'Course', 'Year', 'Status', 'Voted', 'Email', 'Phone']);
            foreach ($students as $s) {
                $votedStatus = $s->has_voted ? 'Yes' : 'No';
                fputcsv($file, [$s->student_id, $s->first_name, $s->last_name, $s->course, $s->year_level, $s->status, $votedStatus, $s->user->email ?? '', $s->phone ?? '']);
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function viewStudent($id)
    {
        $this->dispatch('notify', message: 'Viewing student details for ID: ' . $id, type: 'info');
    }
    public function editStudent($id)
    {
        $this->dispatch('notify', message: 'Edit mode for student ID: ' . $id, type: 'info');
    }

    public function deleteStudent($id)
    {
        $student = Student::findOrFail($id);
        $student->update(['status' => 'inactive']);
        $this->dispatch('notify', message: 'Student account deactivated.', type: 'success');
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

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2>Manage <span>Students</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">View, update, and manage student voter list</p>
            </div>

            <div class="d-flex flex-column align-items-end gap-2">
                <div x-data="{ progress: 0 }"
                    x-on:livewire-upload-start="window.dispatchEvent(new CustomEvent('show-upload-progress'))"
                    x-on:livewire-upload-finish=""
                    x-on:livewire-upload-error="Swal.fire({title: 'Error', text: 'There was an issue uploading the file.', icon: 'error', background: 'rgba(20, 20, 30, 0.95)', color: '#ffffff'})"
                    x-on:livewire-upload-progress="progress = $event.detail.progress; window.dispatchEvent(new CustomEvent('update-upload-progress', {detail: {progress: progress}}))">

                    <input type="file" x-ref="csvInput" wire:model.live="csvFile" class="d-none" accept=".csv">

                    <button type="button" class="btn btn-outline-glow btn-sm" @click="$refs.csvInput.click()">
                        <i class="bi bi-upload me-1"></i>Import CSV
                    </button>
                </div>

                @error('csvFile')
                    <span class="text-danger small"><i
                            class="bi bi-exclamation-triangle me-1"></i>{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3 fade-in-up delay-1">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(56,142,60,0.15); color: var(--accent);"><i
                            class="bi bi-people-fill"></i></div>
                    <div class="stat-value" style="color: var(--accent);">{{ $totalStudents }}</div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up delay-1">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(103,58,183,0.15); color: var(--purple);"><i
                            class="bi bi-person-check-fill"></i></div>
                    <div class="stat-value" style="color: var(--purple);">{{ $votedCount }}</div>
                    <div class="stat-label">Voted</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up delay-2">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(253,203,110,0.15); color: var(--warning);"><i
                            class="bi bi-hourglass-split"></i></div>
                    <div class="stat-value" style="color: var(--warning);">{{ $notVotedCount }}</div>
                    <div class="stat-label">Not Voted</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up delay-3">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(220,53,69,0.15); color: #dc3545;"><i
                            class="bi bi-person-x-fill"></i></div>
                    <div class="stat-value" style="color: #dc3545;">{{ $disabledCount }}</div>
                    <div class="stat-label">Disabled</div>
                </div>
            </div>
        </div>

        <div class="glass-card p-3 mb-4 fade-in-up delay-4">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" wire:model.live.debounce.300ms="search" class="search-glass"
                            placeholder="Search by name or ID...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="course" class="form-control-glass w-100">
                        <option>All Courses</option>
                        <option>IT</option>
                        <option>HRMT</option>
                        <option>HST</option>
                        <option>ECT</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="year" class="form-control-glass w-100">
                        <option>All Years</option>
                        <option value="1">1st Year</option>
                        <option value="2">2nd Year</option>
                        <option value="3">3rd Year</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="status" class="form-control-glass w-100">
                        <option>All Status</option>
                        <option>Voted</option>
                        <option>Not Voted</option>
                        <option>Disabled</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button class="btn btn-outline-glow btn-sm w-100" wire:click="exportStudents">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                </div>
            </div>
        </div>

        <div class="glass-card p-0 overflow-hidden fade-in-up delay-5">
            <div class="table-responsive">
                <table class="table table-glass mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Course & Year</th>
                            <th>Email</th>
                            <th>Vote Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $index => $student)
                            <tr wire:key="student-{{ $student->id }}">
                                <td>{{ $students->firstItem() + $index }}</td>
                                <td><span class="fw-medium">{{ $student->student_id }}</span></td>
                                <td><span class="fw-semibold text-white">{{ $student->first_name }}
                                        {{ $student->last_name }}</span></td>
                                <td>{{ $student->course }} - {{ $student->year_level }}</td>
                                <td><small
                                        class="text-white-50 small-email">{{ $student->user->email ?? 'N/A' }}</small>
                                </td>
                                <td>
                                    @php
                                        $voted = $student->has_voted;
                                        $disabled = in_array($student->status, ['inactive', 'suspended']);
                                        if ($voted) {
                                            $displayText = 'Voted';
                                            $colorClass = 'text-success';
                                            $icon = 'bi-check-circle';
                                        } elseif ($disabled) {
                                            $displayText = ucfirst($student->status);
                                            $colorClass = 'text-danger';
                                            $icon = 'bi-x-circle';
                                        } else {
                                            $displayText = 'Not Voted';
                                            $colorClass = 'text-warning';
                                            $icon = 'bi-clock';
                                        }
                                    @endphp
                                    <span class="fw-bold {{ $colorClass }}"><i
                                            class="bi {{ $icon }} me-1"></i>{{ $displayText }}</span>
                                </td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-center">
                                        <button class="action-btn action-btn-view"
                                            wire:click="viewStudent({{ $student->id }})"><i
                                                class="bi bi-eye"></i></button>
                                        <button class="action-btn action-btn-edit"
                                            wire:click="editStudent({{ $student->id }})"><i
                                                class="bi bi-pencil"></i></button>
                                        <button class="action-btn action-btn-delete"
                                            wire:click="deleteStudent({{ $student->id }})"
                                            wire:confirm="Deactivate this account?"><i
                                                class="bi bi-person-x"></i></button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center p-5 text-white-50">No students found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top border-white-10">{{ $students->links() }}</div>
        </div>
    </main>

    <script>
        document.addEventListener('livewire:initialized', () => {
            let uploadSwal;
            let fakeProgressInterval;

            window.addEventListener("show-upload-progress", () => {
                uploadSwal = Swal.fire({
                    title: "Importing Student Records",
                    html: `
                        <div class="mt-3 text-start">
                            <div class="progress" style="height: 12px; background: rgba(255,255,255,0.1); border-radius: 20px;">
                                <div id="swal-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%; transition: width 0.5s ease;"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-2">
                                <small id="swal-status-text" class="text-white-50">Preparing file...</small>
                                <small id="swal-percent-text" class="fw-bold text-success">0%</small>
                            </div>
                        </div>
                    `,
                    background: "rgba(20, 20, 30, 0.95)",
                    color: "#ffffff",
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                });
            });

            window.addEventListener("update-upload-progress", (event) => {
                const actualProgress = event.detail.progress;
                const progressBar = document.getElementById("swal-progress-bar");
                const statusText = document.getElementById("swal-status-text");
                const percentText = document.getElementById("swal-percent-text");

                if (!progressBar) return;
                let displayProgress = Math.round(actualProgress * 0.85);
                progressBar.style.width = displayProgress + "%";
                percentText.innerText = displayProgress + "%";
                statusText.innerText = "Uploading to server...";

                if (actualProgress >= 100) {
                    statusText.innerText = "Processing database entries...";
                    statusText.classList.replace("text-white-50", "text-success");
                    let creepProgress = 85;
                    clearInterval(fakeProgressInterval);
                    fakeProgressInterval = setInterval(() => {
                        if (creepProgress < 98) {
                            creepProgress += 0.5;
                            progressBar.style.width = creepProgress + "%";
                            percentText.innerText = Math.round(creepProgress) + "%";
                        }
                    }, 400);
                }
            });

            window.addEventListener('confirm-csv-import', event => {
                Swal.close();
                Swal.fire({
                    title: 'Confirm Import',
                    text: `Validated successfully! Import ${event.detail.count} student records?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#388e3c',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, Proceed!',
                    background: "rgba(20, 20, 30, 0.95)",
                    color: "#ffffff",
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Saving to Database...',
                            background: "rgba(20, 20, 30, 0.95)",
                            color: "#ffffff",
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        @this.call('processImport');
                    } else {
                        @this.set('csvFile', null);
                    }
                });
            });

            Livewire.on('notify', (event) => {
                clearInterval(fakeProgressInterval);
                let data = (Array.isArray(event) && event.length > 0) ? event[0] : event;
                let message = data.message || 'Success';
                let type = data.type || 'info';
                let title = type === 'success' ? 'Success!' : (type === 'error' ? 'Error!' : 'Notice');

                Swal.fire({
                    title: title,
                    text: message,
                    icon: type,
                    background: "rgba(20, 20, 30, 0.95)",
                    color: "#ffffff",
                    confirmButtonColor: '#3085d6'
                });
            });
        });
    </script>
</div>

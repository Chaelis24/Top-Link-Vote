<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\{Auth, Session, DB, Hash};
use Illuminate\Support\Str;
use App\Models\{User, Student, Role};

new #[Layout('layouts.app')] #[Title('Manage Students - Admin')] class extends Component {
    use WithFileUploads;

    #[Url]
    public string $search = '';
    #[Url]
    public string $course = 'All Courses';
    #[Url]
    public string $year = 'All Years';
    #[Url]
    public string $status = 'All Status';

    public $csvFile;
    public string $importMessage = '';

    public string $totalStudents = '1,440';
    public string $votedCount = '1,254';
    public string $notVotedCount = '178';
    public string $disabledCount = '8';

    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/', navigate: true);
    }

    public function updatedCsvFile()
    {
        $this->importCSV();
    }

    public function importCSV()
    {
        $this->importMessage = '';

        if (!$this->csvFile) {
            $this->importMessage = 'No file selected.';
            return;
        }

        $this->validate([
            'csvFile' => 'required|file|max:5120',
        ]);

        try {
            $path = $this->csvFile->getRealPath();
            $data = array_map('str_getcsv', file($path));

            array_shift($data);

            DB::beginTransaction();

            $importedCount = 0;

            $studentRole = Role::where('name', 'student')->first();

            foreach ($data as $row) {
                if (empty($row[0])) {
                    continue;
                }

                $studentId = $row[0];

                if (Student::where('student_id', $studentId)->exists()) {
                    continue;
                }

                $actualEmail = !empty($row[12]) ? trim($row[12]) : strtolower($studentId) . '@toplink.edu.ph';

                $user = User::create([
                    'name' => trim($row[1] . ' ' . $row[3]),
                    'email' => $actualEmail,
                    'password' => Hash::make(Str::random(10)),
                ]);

                if ($studentRole) {
                    $user->roles()->attach($studentRole->id);
                }

                $user->student()->create([
                    'student_id' => $studentId,
                    'first_name' => $row[1],
                    'middle_name' => $row[2] ?: null,
                    'last_name' => $row[3],
                    'suffix' => $row[4] ?: null,
                    'course' => $row[5] ?? 'IT',
                    'year_level' => $row[6] ?? 1,
                    'status' => $row[7] ?? 'active',
                    'phone' => $row[8] ?: null,
                    'address' => $row[9] ?: null,
                    'birthday' => $row[10] ?: null,
                    'gender' => $row[11] ?: null,
                ]);

                $importedCount++;
            }

            DB::commit();
            $this->reset('csvFile');

            $this->importMessage = "Success! {$importedCount} students imported.";
            $this->dispatch('notify', message: "Successfully imported {$importedCount} students!", type: 'success');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->reset('csvFile');

            $this->importMessage = 'Error: ' . $e->getMessage();
            $this->dispatch('notify', message: 'Import failed: ' . $e->getMessage(), type: 'error');
        }
    }

    public function exportStudents()
    {
        $students = Student::with('user')->get();

        $fileName = 'students_masterlist_' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = ['Student ID', 'First Name', 'Middle Name', 'Last Name', 'Suffix', 'Course', 'Year Level', 'Status', 'Phone', 'Address', 'Birthday', 'Gender', 'Email'];

        $callback = function () use ($students, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($students as $student) {
                fputcsv($file, [$student->student_id, $student->first_name, $student->middle_name, $student->last_name, $student->suffix, $student->course, $student->year_level, $student->status, $student->phone, $student->address, $student->birthday ? $student->birthday->format('Y-m-d') : '', $student->gender, $student->user ? $student->user->email : '']);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function deleteStudent(int $id)
    {
        $this->dispatch('notify', message: 'Student status updated.', type: 'success');
    }
}; ?>

<div>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2>Manage <span>Students / Voters</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">View, update, and manage student voter list</p>
            </div>

            <div class="d-flex flex-column align-items-end gap-2" x-data>
                <div class="d-flex align-items-center gap-3">

                    <input type="file" x-ref="csvInput" wire:model.live="csvFile" class="d-none" accept=".csv">

                    <button type="button" class="btn btn-outline-glow btn-sm" @click="$refs.csvInput.click()">
                        <span wire:loading.remove wire:target="csvFile">
                            <i class="bi bi-upload me-1"></i>Import CSV
                        </span>
                        <span wire:loading wire:target="csvFile">
                            <span class="spinner-border spinner-border-sm me-1" role="status"
                                aria-hidden="true"></span>Uploading...
                        </span>
                    </button>
                </div>

                @error('csvFile')
                    <span class="text-danger small"><i
                            class="bi bi-exclamation-triangle me-1"></i>{{ $message }}</span>
                @enderror

                @if ($importMessage)
                    <span class="small {{ str_contains($importMessage, 'Error') ? 'text-danger' : 'text-success' }}">
                        {{ $importMessage }}
                    </span>
                @endif
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
                        <option>BSIT</option>
                        <option>BSA</option>
                        <option>BSBA</option>
                        <option>BSCS</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="year" class="form-control-glass w-100">
                        <option>All Years</option>
                        <option>1st Year</option>
                        <option>2nd Year</option>
                        <option>3rd Year</option>
                        <option>4th Year</option>
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
                        <tr>
                            <td>1</td>
                            <td><span class="fw-medium">2024-00123</span></td>
                            <td><span class="fw-semibold text-white">Juan Dela Cruz</span></td>
                            <td>BSIT - 4th Year</td>
                            <td><small class="text-white-50 small-email">juan@toplink.edu.ph</small></td>
                            <td><span class="voter-status-voted"><i class="bi bi-check-circle me-1"></i>Voted</span>
                            </td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button class="action-btn action-btn-view"><i class="bi bi-eye"></i></button>
                                    <button class="action-btn action-btn-edit"><i class="bi bi-pencil"></i></button>
                                    <button class="action-btn action-btn-delete" wire:click="deleteStudent(1)"
                                        wire:confirm="Disable this account?"><i class="bi bi-person-x"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

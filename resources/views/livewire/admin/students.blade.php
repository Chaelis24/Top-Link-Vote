<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Manage Students - Admin')] class extends Component {
    // Search and Filter State
    #[Url]
    public string $search = '';
    #[Url]
    public string $course = 'All Courses';
    #[Url]
    public string $year = 'All Years';
    #[Url]
    public string $status = 'All Status';

    // Stats (Static placeholders)
    public string $totalStudents = '1,440';
    public string $votedCount = '1,254';
    public string $notVotedCount = '178';
    public string $disabledCount = '8';

    /**
     * Handle logout.
     */
    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/', navigate: true);
    }

    /**
     * Start CSV Import process.
     */
    public function importCSV()
    {
        // Logic for CSV Import will go here
        $this->dispatch('notify', message: 'CSV Import started...', type: 'info');
    }

    /**
     * Delete/Disable student record.
     */
    public function deleteStudent(int $id)
    {
        // Logic to delete or disable student
        $this->dispatch('notify', message: 'Student status updated.', type: 'success');
    }

    /**
     * Save a new student to the database.
     */
    public function addStudent()
    {
        // Add Validation and Save logic here
        $this->dispatch('notify', message: 'Student added successfully!', type: 'success');
    }
}; ?>

<div>
    {{-- Sidebar & Navigation --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2>Manage <span>Students / Voters</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">View, update, and manage student voter list</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-glow btn-sm" wire:click="importCSV"><i
                        class="bi bi-upload me-1"></i>Import CSV</button>
                <button class="btn btn-glow btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal"><i
                        class="bi bi-plus-lg me-1"></i>Add Student</button>
            </div>
        </div>

        {{-- Stats Row --}}
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

        {{-- Search & Filters --}}
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
                    <button class="btn btn-outline-glow btn-sm w-100"><i class="bi bi-download me-1"></i>Export</button>
                </div>
            </div>
        </div>

        {{-- Students Table --}}
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

    {{-- Modals (Add Student) --}}
    <div class="modal fade modal-glass" id="addStudentModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2 text-accent"></i>Add Student</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="addStudent">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small">Student ID</label>
                                <input type="text" class="form-control-glass w-100" placeholder="e.g. 2024-00001">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small">Full Name</label>
                                <input type="text" class="form-control-glass w-100" placeholder="Enter full name">
                            </div>
                            {{-- ... more fields ... --}}
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" wire:click="addStudent" class="btn btn-glow btn-sm"
                        data-bs-dismiss="modal">Add Student</button>
                </div>
            </div>
        </div>
    </div>
</div>

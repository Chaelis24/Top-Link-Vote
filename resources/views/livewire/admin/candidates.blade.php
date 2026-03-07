<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Manage Candidates')] class extends Component {
    // Filter States
    public string $search = '';
    public string $positionFilter = 'All Positions';
    public string $statusFilter = 'All Status';

    // Form States (Para sa Add Candidate)
    public string $name = '';
    public string $position = '';
    public string $party = '';
    public string $year_level = '';
    public string $course = '';

    // Dummy Stats (I-connect mo ito sa DB sa future)
    public int $totalCandidates = 12;
    public int $approvedCount = 8;
    public int $pendingCount = 3;
    public int $disqualifiedCount = 1;

    /**
     * Add Candidate Logic
     */
    public function saveCandidate()
    {
        $this->validate([
            'name' => 'required|min:3',
            'position' => 'required',
            'party' => 'required',
            'year_level' => 'required',
            'course' => 'required',
        ]);

        // DB Logic: Candidate::create([...]);

        $this->dispatch('swal', title: 'Success!', text: 'Candidate has been added to the pool.', icon: 'success');

        // I-reset ang form pagkatapos i-save
        $this->reset(['name', 'position', 'party', 'year_level', 'course']);

        // I-close ang modal (Kailangan ng custom JS listener o Alpine)
        $this->dispatch('close-modal');
    }

    public function resetFilters()
    {
        $this->reset('search', 'positionFilter', 'statusFilter');
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
    {{-- Sidebar Elements --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        {{-- Persistent Topbar (No AOS Conflict) --}}
        <div class="topbar" wire:key="admin-topbar">
            <div>
                <h2>Manage <span>Candidates</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Add, edit, and manage election candidates</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-glow btn-sm" data-bs-toggle="modal" data-bs-target="#addCandidateModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Candidate
                </button>
            </div>
        </div>

        {{-- Stats Row (Staggered Animation) --}}
        <div class="row g-3 mb-4">
            @php
                $stats = [
                    [
                        'label' => 'Total Candidates',
                        'val' => $totalCandidates,
                        'color' => 'var(--accent)',
                        'bg' => 'rgba(56,142,60,0.15)',
                        'icon' => 'bi-people-fill',
                    ],
                    [
                        'label' => 'Approved',
                        'val' => $approvedCount,
                        'color' => 'var(--purple)',
                        'bg' => 'rgba(103,58,183,0.15)',
                        'icon' => 'bi-check-circle-fill',
                    ],
                    [
                        'label' => 'Pending',
                        'val' => $pendingCount,
                        'color' => 'var(--warning)',
                        'bg' => 'rgba(253,203,110,0.15)',
                        'icon' => 'bi-hourglass-split',
                    ],
                    [
                        'label' => 'Disqualified',
                        'val' => $disqualifiedCount,
                        'color' => '#dc3545',
                        'bg' => 'rgba(220,53,69,0.15)',
                        'icon' => 'bi-x-circle-fill',
                    ],
                ];
            @endphp
            @foreach ($stats as $index => $s)
                <div class="col-6 col-lg-3"
                    style="animation: fadeInUp 0.5s ease forwards; animation-delay: {{ $index * 0.1 }}s; opacity: 0;">
                    <div class="glass-card stat-card">
                        <div class="stat-icon" style="background: {{ $s['bg'] }}; color: {{ $s['color'] }};"><i
                                class="bi {{ $s['icon'] }}"></i></div>
                        <div class="stat-value" style="color: {{ $s['color'] }};">{{ $s['val'] }}</div>
                        <div class="stat-label">{{ $s['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Search & Filter Bar (Pure CSS Animation) --}}
        <div class="glass-card p-3 mb-4" wire:key="admin-filters"
            style="animation: fadeInUp 0.5s ease forwards; animation-delay: 0.4s; opacity: 0;">
            <div class="row g-3 align-items-center">
                <div class="col-md-5">
                    <div class="search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" wire:model.live.debounce.300ms="search" class="search-glass"
                            placeholder="Search candidates...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select wire:model.live="positionFilter" class="form-control-glass w-100">
                        <option>All Positions</option>
                        <option>President</option>
                        <option>Vice President</option>
                        <option>Secretary</option>
                        <option>Treasurer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select wire:model.live="statusFilter" class="form-control-glass w-100">
                        <option>All Status</option>
                        <option>Approved</option>
                        <option>Pending</option>
                        <option>Disqualified</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button class="btn btn-outline-glow btn-sm w-100" wire:click="resetFilters">
                        <i class="bi bi-funnel me-1"></i>Reset
                    </button>
                </div>
            </div>
        </div>

        {{-- Table Section --}}
        <div class="glass-card p-0 overflow-hidden"
            style="animation: fadeInUp 0.5s ease forwards; animation-delay: 0.5s; opacity: 0;">
            <div class="table-responsive">
                <table class="table table-glass mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Candidate</th>
                            <th>Position</th>
                            <th>Party</th>
                            <th>Votes</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody wire:loading.class="opacity-50">
                        {{-- Sample Row --}}
                        <tr>
                            <td class="text-white-50">1</td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="candidate-avatar-placeholder">JD</div>
                                    <div>
                                        <div class="fw-semibold text-white">Juan Dela Cruz</div>
                                        <small class="text-white-50">BSIT - 4th Year</small>
                                    </div>
                                </div>
                            </td>
                            <td>President</td>
                            <td><span class="badge"
                                    style="background: rgba(56,142,60,0.2); color: var(--accent);">Green Alliance</span>
                            </td>
                            <td><span class="fw-bold" style="color: var(--accent);">557</span></td>
                            <td><span class="badge badge-status badge-open">Approved</span></td>
                            <td>
                                <div class="d-flex gap-2 justify-content-center">
                                    <button class="action-btn action-btn-view"><i class="bi bi-eye"></i></button>
                                    <button class="action-btn action-btn-edit"><i class="bi bi-pencil"></i></button>
                                    <button class="action-btn action-btn-delete"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    {{-- ADD CANDIDATE MODAL --}}
    <div class="modal fade" id="addCandidateModal" tabindex="-1" wire:ignore.self aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-white-10"
                style="background: #0f172a; border: 1px solid rgba(255,255,255,0.1);">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-white fw-bold"><i class="bi bi-person-plus me-2 text-accent"></i>Add
                        Candidate</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form wire:submit.prevent="saveCandidate">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="text-white-50 small mb-1">Full Name</label>
                            <input type="text" wire:model="name"
                                class="form-control-glass w-100 @error('name') is-invalid @enderror"
                                placeholder="e.g. Maria Clara">
                            @error('name')
                                <span class="text-danger x-small">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="text-white-50 small mb-1">Position</label>
                                <select wire:model="position" class="form-control-glass w-100">
                                    <option value="">Select Position</option>
                                    <option>President</option>
                                    <option>Vice President</option>
                                    <option>Secretary</option>
                                    <option>Treasurer</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="text-white-50 small mb-1">Party</label>
                                <input type="text" wire:model="party" class="form-control-glass w-100"
                                    placeholder="Party Name">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="text-white-50 small mb-1">Year Level</label>
                                <select wire:model="year_level" class="form-control-glass w-100">
                                    <option value="">Select Year</option>
                                    <option>1st Year</option>
                                    <option>2nd Year</option>
                                    <option>3rd Year</option>
                                    <option>4th Year</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="text-white-50 small mb-1">Course</label>
                                <input type="text" wire:model="course" class="form-control-glass w-100"
                                    placeholder="e.g. BSCS">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-outline-glow btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-glow btn-sm">
                            <span wire:loading.remove wire:target="saveCandidate">Save Candidate</span>
                            <span wire:loading wire:target="saveCandidate">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Script para i-close ang modal pagkatapos mag-save --}}
<script>
    window.addEventListener('close-modal', event => {
        bootstrap.Modal.getInstance(document.getElementById('addCandidateModal')).hide();
    });
</script>

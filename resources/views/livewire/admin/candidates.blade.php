<?php

use function Livewire\Volt\{state, layout, title, middleware};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

// Setup
layout('layouts.app');
title('Manage Candidates - Admin');

state([
    'search' => '',
    'positionFilter' => 'All Positions',
    'statusFilter' => 'All Status',
    'totalCandidates' => 12,
    'approvedCount' => 8,
    'pendingCount' => 3,
    'disqualifiedCount' => 1,
]);

// Logout function para sa sidebar
$logout = function () {
    Auth::guard('web')->logout();
    Session::invalidate();
    Session::regenerateToken();
    return $this->redirect('/', navigate: true);
};

?>

<div>
    {{-- Sidebar & UI Elements --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar" data-aos="fade-down">
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

        {{-- Stats Row --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(56,142,60,0.15); color: var(--accent);"><i
                            class="bi bi-people-fill"></i></div>
                    <div class="stat-value" style="color: var(--accent);">{{ $totalCandidates }}</div>
                    <div class="stat-label">Total Candidates</div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(103,58,183,0.15); color: var(--purple);"><i
                            class="bi bi-check-circle-fill"></i></div>
                    <div class="stat-value" style="color: var(--purple);">{{ $approvedCount }}</div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(253,203,110,0.15); color: var(--warning);"><i
                            class="bi bi-hourglass-split"></i></div>
                    <div class="stat-value" style="color: var(--warning);">{{ $pendingCount }}</div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="400">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(220,53,69,0.15); color: #dc3545;"><i
                            class="bi bi-x-circle-fill"></i></div>
                    <div class="stat-value" style="color: #dc3545;">{{ $disqualifiedCount }}</div>
                    <div class="stat-label">Disqualified</div>
                </div>
            </div>
        </div>

        {{-- Search & Filter --}}
        <div class="glass-card p-3 mb-4" data-aos="fade-up" data-aos-delay="500">
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
                    <button class="btn btn-outline-glow btn-sm w-100" wire:click="$set('search', '')"><i
                            class="bi bi-funnel me-1"></i>Reset</button>
                </div>
            </div>
        </div>

        {{-- Table Section --}}
        <div class="glass-card p-0 overflow-hidden" data-aos="fade-up" data-aos-delay="600">
            <div class="table-responsive">
                <table class="table table-glass mb-0 w-full">
                    <thead class="text-white">
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
                    <tbody>
                        {{-- Static Row Example --}}
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
                                    <button class="action-btn action-btn-view" data-bs-toggle="modal"
                                        data-bs-target="#viewCandidateModal"><i class="bi bi-eye"></i></button>
                                    <button class="action-btn action-btn-edit" data-bs-toggle="modal"
                                        data-bs-target="#editCandidateModal"><i class="bi bi-pencil"></i></button>
                                    <button class="action-btn action-btn-delete"><i class="bi bi-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    {{-- Add Candidate Modal --}}
    <div class="modal fade modal-glass" id="addCandidateModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"
                            style="color: var(--accent);"></i>Add Candidate</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label-glass">Full Name</label>
                            <input type="text" class="form-control-glass w-100" placeholder="Enter full name">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label-glass">Position</label>
                                <select class="form-control-glass w-100">
                                    <option>Select Position</option>
                                    <option>President</option>
                                    <option>Vice President</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label-glass">Party</label>
                                <select class="form-control-glass w-100">
                                    <option>Select Party</option>
                                    <option>Green Alliance</option>
                                    <option>Purple Wave</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-glass">Photo</label>
                            <input type="file" class="form-control-glass w-100" accept="image/*">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-glow btn-sm">Save Candidate</button>
                </div>
            </div>
        </div>
    </div>

    {{-- View Candidate Modal --}}
    <div class="modal fade modal-glass" id="viewCandidateModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-lines-fill me-2"
                            style="color: var(--accent);"></i>Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="candidate-avatar-placeholder mx-auto mb-3"
                        style="width: 90px; height: 90px; font-size: 2rem;">JD</div>
                    <h4 class="fw-bold mb-1">Juan Dela Cruz</h4>
                    <p>BSIT - 4th Year</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Candidate Modal --}}
    <div class="modal fade modal-glass" id="editCandidateModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"
                            style="color: var(--purple);"></i>Edit Candidate</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    {{-- Form content same as add modal --}}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-glow btn-sm">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

</div>

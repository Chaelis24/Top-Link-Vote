<?php

use function Livewire\Volt\{state, layout, title};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

layout('layouts.app');
title('Manage Positions - Admin');

state([
    // Stats Data (Static placeholders)
    'totalPositions' => 6,
    'assignedCandidates' => 12,
    'filledPositions' => 4,
    'unfilledPositions' => 2,
]);

// Logout function para sa sidebar
$logout = function () {
    Auth::guard('web')->logout();
    Session::invalidate();
    Session::regenerateToken();
    return $this->redirect('/', navigate: true);
};

// Function para sa pag-delete (placeholder)
$deletePosition = function ($id) {
    // Logic for deletion here
    $this->dispatch('notify', message: 'Position removed successfully!', type: 'success');
};

?>

<div>
    {{-- Sidebar & UI Elements --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar" data-aos="fade-down">
            <div>
                <h2>Manage <span>Positions</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Add, edit, or remove election positions</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-glow btn-sm" data-bs-toggle="modal" data-bs-target="#addPositionModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Position
                </button>
            </div>
        </div>

        {{-- Stats Row --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3" data-aos="fade-up">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(56,142,60,0.15); color: var(--accent);"><i
                            class="bi bi-diagram-3-fill"></i></div>
                    <div class="stat-value" style="color: var(--accent);">{{ $totalPositions }}</div>
                    <div class="stat-label">Total Positions</div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(103,58,183,0.15); color: var(--purple);"><i
                            class="bi bi-people-fill"></i></div>
                    <div class="stat-value" style="color: var(--purple);">{{ $assignedCandidates }}</div>
                    <div class="stat-label">Candidates Assigned</div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(56,142,60,0.15); color: var(--success);"><i
                            class="bi bi-check-all"></i></div>
                    <div class="stat-value" style="color: var(--success);">{{ $filledPositions }}</div>
                    <div class="stat-label">Filled Positions</div>
                </div>
            </div>
            <div class="col-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(253,203,110,0.15); color: var(--warning);"><i
                            class="bi bi-exclamation-triangle-fill"></i></div>
                    <div class="stat-value" style="color: var(--warning);">{{ $unfilledPositions }}</div>
                    <div class="stat-label">Unfilled</div>
                </div>
            </div>
        </div>

        {{-- Position Hierarchy List --}}
        <div class="glass-card p-4 mb-4" data-aos="fade-up" data-aos-delay="400">
            <h6 class="fw-bold mb-3"><i class="bi bi-list-nested me-2" style="color: var(--accent);"></i>Position
                Hierarchy</h6>

            {{-- Position Card: President --}}
            <div class="position-card glass-card mb-3">
                <div class="position-icon" style="background: rgba(56,142,60,0.15); color: var(--accent);"><i
                        class="bi bi-star-fill"></i></div>
                <div class="flex-grow-1">
                    <h6 class="fw-semibold mb-0">President</h6>
                    <small class="text-white-50">Highest executive position • Max 1 winner</small>
                </div>
                <div class="position-count" style="color: var(--accent);">3 candidates</div>
                <div class="d-flex gap-2">
                    <button class="action-btn action-btn-edit" data-bs-toggle="modal"
                        data-bs-target="#editPositionModal"><i class="bi bi-pencil"></i></button>
                    <button class="action-btn action-btn-delete"
                        wire:confirm="Are you sure you want to delete this position?"><i
                            class="bi bi-trash"></i></button>
                </div>
            </div>

            {{-- Position Card: Vice President --}}
            <div class="position-card glass-card mb-3">
                <div class="position-icon" style="background: rgba(103,58,183,0.15); color: var(--purple);"><i
                        class="bi bi-shield-fill"></i></div>
                <div class="flex-grow-1">
                    <h6 class="fw-semibold mb-0">Vice President</h6>
                    <small class="text-white-50">Second in command • Max 1 winner</small>
                </div>
                <div class="position-count" style="color: var(--purple);">2 candidates</div>
                <div class="d-flex gap-2">
                    <button class="action-btn action-btn-edit" data-bs-toggle="modal"
                        data-bs-target="#editPositionModal"><i class="bi bi-pencil"></i></button>
                    <button class="action-btn action-btn-delete"><i class="bi bi-trash"></i></button>
                </div>
            </div>

            {{-- Position Card: Senator --}}
            <div class="position-card glass-card">
                <div class="position-icon" style="background: rgba(253,203,110,0.15); color: var(--warning);"><i
                        class="bi bi-person-badge-fill"></i></div>
                <div class="flex-grow-1">
                    <h6 class="fw-semibold mb-0">Senator</h6>
                    <small class="text-white-50">Student representatives • Max 6 winners</small>
                </div>
                <div class="position-count text-white-50">3 candidates</div>
                <div class="d-flex gap-2">
                    <button class="action-btn action-btn-edit" data-bs-toggle="modal"
                        data-bs-target="#editPositionModal"><i class="bi bi-pencil"></i></button>
                    <button class="action-btn action-btn-delete"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>
    </main>

    {{-- Add Position Modal --}}
    <div class="modal fade modal-glass" id="addPositionModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-diagram-3-fill me-2"
                            style="color: var(--accent);"></i>Add Position</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Position Title</label>
                            <input type="text" class="form-control-glass w-100" placeholder="e.g. Auditor">
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Description</label>
                            <textarea class="form-control-glass w-100" rows="2" placeholder="Brief description..."></textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label text-white-50 small">Max Winners</label>
                                <input type="number" class="form-control-glass w-100" value="1"
                                    min="1">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-white-50 small">Priority Order</label>
                                <input type="number" class="form-control-glass w-100" value="1"
                                    min="1">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-glow btn-sm">Save Position</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Position Modal --}}
    <div class="modal fade modal-glass" id="editPositionModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"
                            style="color: var(--purple);"></i>Edit Position</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Position Title</label>
                            <input type="text" class="form-control-glass w-100" value="President">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label text-white-50 small">Max Winners</label>
                                <input type="number" class="form-control-glass w-100" value="1">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-white-50 small">Status</label>
                                <select class="form-control-glass w-100">
                                    <option>Active</option>
                                    <option>Inactive</option>
                                </select>
                            </div>
                        </div>

                        {{-- Current Candidates Mini-list --}}
                        <div class="glass p-3 mt-2" style="border-radius: 12px;">
                            <h6 class="fw-semibold mb-2" style="font-size: 0.85rem; color: var(--accent);"><i
                                    class="bi bi-people me-2"></i>Current Candidates</h6>
                            <div class="d-flex align-items-center gap-2 py-2 border-bottom border-white-5 text-white"
                                style="font-size: 0.85rem;">
                                <div class="avatar-sm-circle">JD</div>
                                <span>Juan Dela Cruz</span>
                                <span class="badge badge-status badge-open ms-auto"
                                    style="font-size: 0.65rem;">Approved</span>
                            </div>
                            <div class="d-flex align-items-center gap-2 py-2 text-white" style="font-size: 0.85rem;">
                                <div class="avatar-sm-circle" style="background: var(--purple);">MS</div>
                                <span>Maria Santos</span>
                                <span class="badge badge-status badge-open ms-auto"
                                    style="font-size: 0.65rem;">Approved</span>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-glow btn-sm">Update Position</button>
                </div>
            </div>
        </div>
    </div>
</div>

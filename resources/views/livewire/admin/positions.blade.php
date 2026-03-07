<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Manage Positions')] class extends Component {
    // Stats State
    public int $totalPositions = 6;
    public int $assignedCandidates = 12;
    public int $filledPositions = 4;
    public int $unfilledPositions = 2;

    /**
     * Handle the admin logout.
     */
    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/', navigate: true);
    }

    /**
     * Delete a specific position.
     */
    public function deletePosition(int $id)
    {
        // Logic for deletion (e.g., Position::destroy($id);)

        $this->dispatch('notify', message: 'Position removed successfully!', type: 'success');
    }

    /**
     * Placeholder for saving a new position.
     */
    public function savePosition()
    {
        // Validation and creation logic would go here.

        $this->dispatch('notify', message: 'Position created successfully!', type: 'success');
    }
}; ?>

<div>
    {{-- Sidebar & UI Elements --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
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
            <div class="col-6 col-lg-3 fade-in-up delay-1">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(56,142,60,0.15); color: var(--accent);"><i
                            class="bi bi-diagram-3-fill"></i></div>
                    <div class="stat-value" style="color: var(--accent);">{{ $totalPositions }}</div>
                    <div class="stat-label">Total Positions</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up delay-1">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(103,58,183,0.15); color: var(--purple);"><i
                            class="bi bi-people-fill"></i></div>
                    <div class="stat-value" style="color: var(--purple);">{{ $assignedCandidates }}</div>
                    <div class="stat-label">Candidates Assigned</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up delay-2">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(56,142,60,0.15); color: var(--success);"><i
                            class="bi bi-check-all"></i></div>
                    <div class="stat-value" style="color: var(--success);">{{ $filledPositions }}</div>
                    <div class="stat-label">Filled Positions</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up delay-3">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(253,203,110,0.15); color: var(--warning);"><i
                            class="bi bi-exclamation-triangle-fill"></i></div>
                    <div class="stat-value" style="color: var(--warning);">{{ $unfilledPositions }}</div>
                    <div class="stat-label">Unfilled</div>
                </div>
            </div>
        </div>

        {{-- Position Hierarchy List --}}
        <div class="glass-card p-4 mb-4 fade-in-up delay-4">
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
                    <button class="action-btn action-btn-delete" wire:click="deletePosition(1)"
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
                    <button class="action-btn action-btn-delete" wire:click="deletePosition(2)"
                        wire:confirm="Are you sure?"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>
    </main>

    {{-- Add Position Modal --}}
    <div class="modal fade modal-glass" id="addPositionModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-diagram-3-fill me-2" style="color: var(--accent);"></i>Add
                        Position</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="savePosition">
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Position Title</label>
                            <input type="text" class="form-control-glass w-100" placeholder="e.g. Auditor">
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
                    <button type="button" wire:click="savePosition" class="btn btn-glow btn-sm"
                        data-bs-dismiss="modal">Save Position</button>
                </div>
            </div>
        </div>
    </div>
</div>

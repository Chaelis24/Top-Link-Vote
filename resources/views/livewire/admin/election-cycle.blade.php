<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Election Cycle')] class extends Component {
    // Election Settings Toggle States
    public bool $allowVoting = true;
    public bool $showResults = false;
    public bool $registrationOpen = false;
    public bool $emailNotifications = true;

    // Cycle Data
    public int $progress = 65;
    public string $currentCycle = 'S.Y. 2024-2025 Election';

    // Form States (New Cycle / Edit Dates)
    public string $cycle_name = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $description = '';

    /**
     * Save New Election Cycle
     */
    public function createNewCycle()
    {
        $this->validate([
            'cycle_name' => 'required|min:5',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        // Logic para i-save sa Database (Example: Election::create(...))

        $this->dispatch('swal', title: 'New Cycle Started!', text: 'The election cycle has been initialized.', icon: 'success');
        $this->reset(['cycle_name', 'start_date', 'end_date', 'description']);
        $this->dispatch('close-modal', id: 'newCycleModal');
    }

    /**
     * Update Current Dates
     */
    public function updateDates()
    {
        $this->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $this->dispatch('swal', title: 'Updated!', text: 'Election dates have been modified.', icon: 'success');
        $this->dispatch('close-modal', id: 'editDatesModal');
    }

    public function toggleSetting(string $setting)
    {
        if (property_exists($this, $setting)) {
            $this->{$setting} = !$this->{$setting};
        }
    }

    public function endElection()
    {
        $this->allowVoting = false;
        $this->progress = 100;
        $this->dispatch('swal', title: 'Finalized!', text: 'Election cycle has been ended.', icon: 'info');
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
    {{-- Sidebar & UI Elements --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        {{-- Persistent Topbar --}}
        <div class="topbar" wire:key="cycle-topbar">
            <div>
                <h2>Election <span>Cycle</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Configure election dates, phases, and settings
                </p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-glow btn-sm" data-bs-toggle="modal" data-bs-target="#newCycleModal">
                    <i class="bi bi-plus-lg me-1"></i>New Election Cycle
                </button>
            </div>
        </div>

        <div class="row g-4">
            {{-- Current Cycle Status --}}
            <div class="col-lg-8" style="animation: fadeInUp 0.5s ease forwards;">
                <div class="glass-card cycle-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-calendar-event-fill me-2"
                                    style="color: var(--accent);"></i>{{ $currentCycle }}</h5>
                            <small class="text-white-50">Student Council General Election</small>
                        </div>
                        <span class="badge badge-status badge-open">
                            <span class="pulse-dot me-1"
                                style="background: var(--success); width: 8px; height: 8px;"></span> Active
                        </span>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-white-50">Election Progress</small>
                            <small class="fw-bold" style="color: var(--accent);">{{ $progress }}%</small>
                        </div>
                        <div class="progress-slim">
                            <div class="bar"
                                style="width: {{ $progress }}%; background: linear-gradient(90deg, var(--accent), var(--purple));">
                            </div>
                        </div>
                    </div>

                    {{-- Timeline --}}
                    <div class="cycle-timeline">
                        <div class="cycle-step completed">
                            <div class="cycle-step-title" style="color: var(--accent);">Filing of Candidacy</div>
                            <div class="cycle-step-date">Jan 15 - Jan 31, 2025</div>
                        </div>
                        <div class="cycle-step completed purple">
                            <div class="cycle-step-title" style="color: var(--purple);">Campaign Period</div>
                            <div class="cycle-step-date">Feb 1 - Feb 14, 2025</div>
                        </div>
                        <div class="cycle-step active">
                            <div class="cycle-step-title" style="color: var(--accent);">Voting Period</div>
                            <div class="cycle-step-date">Feb 15 - Feb 28, 2025</div>
                        </div>
                        <div class="cycle-step upcoming">
                            <div class="cycle-step-title">Vote Counting & Results</div>
                            <div class="cycle-step-date">Mar 1 - Mar 3, 2025</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Settings Panel --}}
            <div class="col-lg-4" style="animation: fadeInUp 0.5s ease forwards; animation-delay: 0.2s;">
                <div class="glass-card cycle-card h-100">
                    <h5 class="fw-bold mb-4"><i class="bi bi-gear-fill me-2" style="color: var(--purple);"></i>Settings
                    </h5>

                    @foreach ([['key' => 'allowVoting', 'label' => 'Allow Voting', 'desc' => 'Enable portal'], ['key' => 'showResults', 'label' => 'Show Results', 'desc' => 'Live results'], ['key' => 'registrationOpen', 'label' => 'Registration', 'desc' => 'Candidate filing'], ['key' => 'emailNotifications', 'label' => 'Email', 'desc' => 'Confirmations']] as $setting)
                        <div class="settings-item d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fw-medium small">{{ $setting['label'] }}</div>
                                <small class="text-white-50" style="font-size: 0.7rem;">{{ $setting['desc'] }}</small>
                            </div>
                            <button wire:click="toggleSetting('{{ $setting['key'] }}')"
                                class="toggle-switch {{ $this->{$setting['key']} ? 'active' : '' }}"></button>
                        </div>
                    @endforeach

                    <div class="mt-4 pt-3 border-top border-white-10">
                        <button class="btn btn-outline-glow btn-sm w-100 mb-2" data-bs-toggle="modal"
                            data-bs-target="#editDatesModal">
                            <i class="bi bi-pencil me-1"></i>Edit Dates
                        </button>
                        <button class="btn btn-outline-glow btn-sm w-100 text-danger border-danger"
                            wire:click="endElection" wire:confirm="Are you sure?">
                            <i class="bi bi-stop-circle me-1"></i>End Election
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- NEW CYCLE MODAL --}}
        <div class="modal fade" id="newCycleModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content glass-card"
                    style="background: #0f172a; border: 1px solid rgba(255,255,255,0.1);">
                    <div class="modal-header border-0">
                        <h5 class="modal-title text-white fw-bold">New Election Cycle</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form wire:submit.prevent="createNewCycle">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="text-white-50 small mb-1">Cycle Name</label>
                                <input type="text" wire:model="cycle_name" class="form-control-glass w-100"
                                    placeholder="e.g. S.Y. 2025-2026 Election">
                                @error('cycle_name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="text-white-50 small mb-1">Start Date</label>
                                    <input type="date" wire:model="start_date" class="form-control-glass w-100">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="text-white-50 small mb-1">End Date</label>
                                    <input type="date" wire:model="end_date" class="form-control-glass w-100">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-outline-glow btn-sm"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-glow btn-sm">Start New Cycle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- EDIT DATES MODAL --}}
        <div class="modal fade" id="editDatesModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content glass-card"
                    style="background: #0f172a; border: 1px solid rgba(255,255,255,0.1);">
                    <div class="modal-header border-0">
                        <h5 class="modal-title text-white fw-bold">Edit Election Dates</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form wire:submit.prevent="updateDates">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="text-white-50 small mb-1">Start Date</label>
                                    <input type="date" wire:model="start_date" class="form-control-glass w-100">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="text-white-50 small mb-1">End Date</label>
                                    <input type="date" wire:model="end_date" class="form-control-glass w-100">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-outline-glow btn-sm"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-glow btn-sm">Update Dates</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
    window.addEventListener('close-modal', event => {
        const modalId = event.detail.id;
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            bootstrap.Modal.getInstance(modalElement).hide();
        }
    });
</script>

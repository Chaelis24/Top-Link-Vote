<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title};
use Illuminate\Support\Facades\{Auth, Session, DB};
use App\Models\ElectionCycle;

new #[Layout('layouts.app')] #[Title('Election Cycle')] class extends Component {
    public bool $allowVoting = true;
    public bool $showResults = false;
    public bool $registrationOpen = false;
    public bool $emailNotifications = true;

    public int $progress = 0;
    public string $currentCycle = 'No Active Cycle';
    public string $status = 'inactive';

    public string $cycle_name = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $description = '';

    public function mount()
    {
        $active = ElectionCycle::where('status', 'active')->first();

        if ($active) {
            $this->currentCycle = $active->name;
            $this->status = $active->status;
            $this->start_date = $active->start_date ? $active->start_date->format('Y-m-d') : '';
            $this->end_date = $active->end_date ? $active->end_date->format('Y-m-d') : '';
            $this->description = $active->description ?? '';

            if ($active->start_date && $active->end_date) {
                $start = $active->start_date;
                $end = $active->end_date;
                $now = now();

                if ($now->gt($end)) {
                    $this->progress = 100;
                } elseif ($now->lt($start)) {
                    $this->progress = 0;
                } else {
                    $total = $start->diffInSeconds($end);
                    $elapsed = $start->diffInSeconds($now);
                    $this->progress = (int) (($elapsed / $total) * 100);
                }
            }
        }
    }

    public function castVote($candidateId)
    {
        // Logic here
    }

    public function createNewCycle()
    {
        $this->validate([
            'cycle_name' => 'required|min:5',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        preg_match('/\d{4}-\d{4}/', $this->cycle_name, $matches);
        $academicYear = $matches[0] ?? now()->format('Y') . '-' . now()->addYear()->format('Y');

        DB::transaction(function () use ($academicYear) {
            ElectionCycle::where('status', 'active')->update(['status' => 'completed']);

            ElectionCycle::create([
                'name' => $this->cycle_name,
                'academic_year' => $academicYear,
                'description' => $this->description ?: 'Student Council General Election',
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
                'status' => 'active',
                'campaign_start' => $this->start_date,
                'campaign_end' => $this->end_date,
                'voting_start' => $this->start_date,
                'voting_end' => $this->end_date,
            ]);
        });

        $this->dispatch('swal', title: 'New Cycle Started!', text: 'The election cycle has been initialized.', icon: 'success');
        return $this->redirect('/admin/election-cycle', navigate: true);
    }

    public function updateDates()
    {
        $this->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $active = ElectionCycle::where('status', 'active')->first();
        if ($active) {
            $active->update([
                'start_date' => $this->start_date,
                'end_date' => $this->end_date,
            ]);
        }

        $this->dispatch('swal', title: 'Updated!', text: 'Election dates have been modified.', icon: 'success');
        return $this->redirect('/admin/election-cycle', navigate: true);
    }

    public function toggleSetting(string $setting)
    {
        if (property_exists($this, $setting)) {
            $this->{$setting} = !$this->{$setting};
        }
    }

    public function endElection()
    {
        $active = ElectionCycle::where('status', 'active')->first();
        if ($active) {
            $active->update(['status' => 'completed']);
        }

        $this->allowVoting = false;
        $this->progress = 100;
        $this->status = 'completed';

        return $this->redirect('/admin/election-cycle', navigate: true);
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
            <div class="col-lg-8" style="animation: fadeInUp 0.5s ease forwards;">
                <div class="glass-card cycle-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h5 class="fw-bold mb-1"><i class="bi bi-calendar-event-fill me-2"
                                    style="color: var(--accent);"></i>{{ $currentCycle }}</h5>
                            <small
                                class="text-white-50">{{ $description ?: 'Student Council General Election' }}</small>
                        </div>
                        <span
                            class="badge badge-status {{ $status === 'active' && $progress < 100 ? 'badge-open' : 'bg-danger' }}">
                            <span class="pulse-dot me-1"
                                style="background: {{ $status === 'active' && $progress < 100 ? 'var(--success)' : '#ff4d4d' }}; width: 8px; height: 8px;">
                            </span>
                            {{ $status === 'active' && $progress < 100 ? 'Active' : 'Closed' }}
                        </span>
                    </div>

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

                    <div class="cycle-timeline">
                        <div class="cycle-step completed">
                            <div class="cycle-step-title" style="color: var(--accent);">Initialization</div>
                            <div class="cycle-step-date">Cycle Created</div>
                        </div>
                        <div class="cycle-step {{ $progress > 50 ? 'completed purple' : '' }}">
                            <div class="cycle-step-title">Campaign Period</div>
                            <div class="cycle-step-date">In Progress</div>
                        </div>
                        <div class="cycle-step {{ $progress < 100 ? 'active' : 'completed' }}">
                            <div class="cycle-step-title">Voting Period</div>
                            <div class="cycle-step-date text-white-50">{{ $start_date ?: 'TBD' }} -
                                {{ $end_date ?: 'TBD' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4" style="animation: fadeInUp 0.5s ease forwards; animation-delay: 0.2s;">
                <div class="glass-card cycle-card h-100">
                    <h5 class="fw-bold mb-4 text-white"><i class="bi bi-gear-fill me-2"
                            style="color: var(--purple);"></i>Settings</h5>

                    @foreach ([['key' => 'allowVoting', 'label' => 'Allow Voting', 'desc' => 'Enable portal'], ['key' => 'showResults', 'label' => 'Show Results', 'desc' => 'Live results'], ['key' => 'registrationOpen', 'label' => 'Registration', 'desc' => 'Candidate filing'], ['key' => 'emailNotifications', 'label' => 'Email', 'desc' => 'Confirmations']] as $setting)
                        <div class="settings-item d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fw-medium small text-white">{{ $setting['label'] }}</div>
                                <small class="text-white-50" style="font-size: 0.7rem;">{{ $setting['desc'] }}</small>
                            </div>
                            <button wire:click="toggleSetting('{{ $setting['key'] }}')"
                                class="toggle-switch {{ $this->{$setting['key']} ? 'active' : '' }}"></button>
                        </div>
                    @endforeach

                    <div class="mt-4 pt-3 border-top border-white-10">
                        <button class="btn btn-outline-glow btn-sm w-100 mb-2 text-white" data-bs-toggle="modal"
                            data-bs-target="#editDatesModal">
                            <i class="bi bi-pencil me-1"></i>Edit Dates
                        </button>
                        <button class="btn btn-outline-glow btn-sm w-100 text-danger border-danger"
                            wire:click="endElection" wire:confirm="Are you sure you want to end this election?">
                            <i class="bi bi-stop-circle me-1"></i>End Election
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="newCycleModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content glass-card border-white-10" style="background: #0f172a;">
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

        <div class="modal fade" id="editDatesModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content glass-card border-white-10" style="background: #0f172a;">
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

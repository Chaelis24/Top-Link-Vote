<?php

use Livewire\Volt\Component;
use App\Traits\AuthenticatesLogout;
use App\Http\Requests\Admin\PositionRequest;
use Illuminate\Support\Facades\{Auth, Session};
use Livewire\Attributes\{Layout, Title, Computed};
use App\Models\{Position, ElectionCycle, Candidate};

new #[Layout('layouts.admin')] #[Title('Manage Positions')] class extends Component {
    use App\Traits\AuthenticatesLogout;

    public string $name = '';
    public string $student_department = '';
    public int $max_winners = 1;
    public int $priority = 1;
    public ?int $editingId = null;

    #[Computed]
    public function activeCycle()
    {
        return ElectionCycle::getActiveCycle();
    }

    #[Computed]
    public function stats()
    {
        $cycle = $this->activeCycle;
        if (!$cycle) {
            return ['total' => 0, 'candidates' => 0, 'filled' => 0, 'unfilled' => 0];
        }

        $cycleId = $cycle->id;
        $total = Position::where('election_cycle_id', $cycleId)->count();
        $candidatesCount = Candidate::where('election_cycle_id', $cycleId)->count();
        $filled = Position::where('election_cycle_id', $cycleId)->whereHas('candidates', fn($q) => $q->where('status', 'approved'))->count();

        return [
            'total' => $total,
            'candidates' => Candidate::where('election_cycle_id', $cycleId)->count(),
            'filled' => $filled,
            'unfilled' => max(0, $total - $filled),
        ];
    }

    #[Computed]
    public function positions()
    {
        if (!$this->activeCycle) {
            return collect();
        }

        return Position::where('election_cycle_id', $this->activeCycle->id)->orderBy('priority', 'asc')->get();
    }

    public function savePosition()
    {
        if (!$this->activeCycle) {
            $this->dispatch('swal', ['title' => 'Error', 'text' => 'No election cycle found.', 'icon' => 'error']);
            return;
        }

        $request = new PositionRequest();
        $this->validate($request->rules());

        if ($this->editingId) {
            $existingCount = Candidate::where('position_id', $this->editingId)->where('status', 'approved')->count();

            if ($this->max_winners < $existingCount) {
                $this->dispatch('swal', [
                    'title' => 'Error',
                    'text' => 'The Max Winners cannot be set lower than the current number of approved candidates (' . $existingCount . ').',
                    'icon' => 'error',
                ]);
                return;
            }
        }

        Position::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'max_winners' => $this->max_winners,
                'priority' => $this->priority,
                'election_cycle_id' => $this->activeCycle->id,
                'student_department' => $this->student_department,
                'is_active' => true,
            ],
        );

        $this->reset(['name', 'max_winners', 'priority', 'editingId', 'student_department']);
        $this->dispatch('swal', [
            'title' => 'Position Saved',
            'text' => 'The position details have been updated.',
            'icon' => 'success',
        ]);
    }

    public function editPosition(int $id)
    {
        $pos = Position::findOrFail($id);
        $this->editingId = $pos->id;
        $this->name = $pos->name;
        $this->max_winners = $pos->max_winners;
        $this->priority = $pos->priority;
        $this->student_department = $pos->student_department;
    }

    public function deletePosition(int $id)
    {
        Position::destroy($id);
        $this->dispatch('swal', [
            'title' => 'Deleted',
            'text' => 'Position has been removed.',
            'icon' => 'warning',
        ]);
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
                <h2 class="fw-bold text-primary">Manage <span class="text-accent">Positions</span></h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Add Position for Student Election</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <x-button variant="glow" class="w-full xs:w-auto px-4 md:px-6" data-bs-toggle="modal"
                    data-bs-target="#addPositionModal" wire:click="$set('editingId', null)">
                    <i class="bi bi-plus-lg fs-7"></i>
                    <span class="hidden md:inline ms-1">Add Position</span>
                </x-button>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6 col-lg-4">
                <div class="glass-card stat-card border-0 shadow-sm py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon-sm bg-primary-soft text-primary"><i class="bi bi-diagram-3"></i></div>
                        <div>
                            <div class="stat-value-sm">{{ $this->stats['total'] }}</div>
                            <div class="stat-label">Total Positions</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="glass-card stat-card border-0 shadow-sm py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon-sm bg-purple-soft text-purple"><i class="bi bi-people"></i></div>
                        <div>
                            <div class="stat-value-sm text-purple">{{ $this->stats['candidates'] }}</div>
                            <div class="stat-label">Candidates</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="glass-card stat-card border-0 shadow-sm py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon-sm bg-success-soft text-success"><i class="bi bi-check-all"></i></div>
                        <div>
                            <div class="stat-value-sm text-success">{{ $this->stats['filled'] }}</div>
                            <div class="stat-label">Filled</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="glass-card p-4 border-0 shadow-sm">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h6 class="fw-bold text-primary mb-0"><i class="bi bi-list-nested me-2"></i>Position</h6>
            </div>

            @forelse($this->positions as $pos)
                <div class="position-row glass-card mb-3 p-3 d-flex align-items-center"
                    wire:key="pos-{{ $pos->id }}">
                    <div class="priority-badge me-3">{{ $pos->priority }}</div>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold text-dark mb-0">{{ $pos->name }}</h6>
                        <small class="text-muted">Max Winners: <span
                                class="fw-bold">{{ $pos->max_winners }}</span></small>
                    </div>
                    <div class="text-end me-4 d-none d-md-block">
                        <div class="fw-bold text-accent">{{ $pos->candidates_count }}</div>
                        <div class="tiny text-muted uppercase fw-bold">Candidates</div>
                    </div>
                    <div class="d-flex gap-2">
                        <x-icon-button variant="edit" wire:click="editPosition({{ $pos->id }})"
                            data-bs-toggle="modal" data-bs-target="#addPositionModal">
                            <i class="bi bi-pencil-square"></i>
                        </x-icon-button>

                        <x-icon-button variant="delete" x-data
                            @click="
                                Swal.fire({
                                    title: 'Are you sure?',
                                    text: 'You won’t be able to revert this position once deleted!',
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#dc3545',
                                    cancelButtonColor: '#6c757d',
                                    confirmButtonText: 'Yes, delete it!',
                                    cancelButtonText: 'Cancel'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        $wire.deletePosition({{ $pos->id }});
                                    }
                                })
                            ">
                            <i class="bi bi-trash"></i>
                        </x-icon-button>
                    </div>
                </div>
            @empty
                <div class="text-center py-5">
                    <p class="text-muted">No positions found for the current election cycle.</p>
                </div>
            @endforelse
        </div>
    </main>

    <div class="modal fade" id="addPositionModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white p-4">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-diagram-3 me-2"></i>
                        {{ $editingId ? 'Update Position' : 'Create Position' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form wire:submit.prevent="savePosition">
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted uppercase">Position Title</label>
                            <input type="text" wire:model="name" class="form-control-modern"
                                placeholder="e.g. President, Auditor...">
                            @error('name')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted uppercase">Max Winners</label>
                                <input type="number" wire:model="max_winners" class="form-control-modern"
                                    min="1" max="10" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold text-muted uppercase">Priority Order</label>
                                <input type="number" wire:model="priority" class="form-control-modern" min="1"
                                    required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer bg-light p-3">
                    <x-button variant="gray" data-bs-dismiss="modal" width="100px">
                        Cancel
                    </x-button>
                    <x-button variant="glow" wire:click="savePosition" width="160px"
                        height="42px">
                        {{ $editingId ? 'Save Changes' : 'Create Position' }}
                    </x-button>
                </div>
            </div>
        </div>
    </div>
</div>

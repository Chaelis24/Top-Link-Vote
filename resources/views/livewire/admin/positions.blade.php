<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Computed};
use Illuminate\Support\Facades\{Auth, Session};
use App\Models\{Position, ElectionCycle, Candidate};

new #[Layout('layouts.app')] #[Title('Manage Positions')] class extends Component {
    // Form State
    public string $name = '';
    public string $description = '';
    public int $max_winners = 1;
    public int $priority = 1;
    public ?int $editingId = null;

    /**
     * Get the current election cycle.
     * Ginawa nating mas flexible para makuha ang data kahit hindi pa naka-is_active.
     */
    #[Computed]
    public function activeCycle()
    {
        // Una, subukan makuha ang explicitly active.
        $active = ElectionCycle::where('is_active', true)->first();

        // Kung wala, kunin ang pinaka-latest na cycle (ID 1 sa case mo).
        return $active ?: ElectionCycle::first();
    }

    /**
     * Fetch statistics based on database records.
     */
    #[Computed]
    public function stats()
    {
        if (!$this->activeCycle) {
            return ['total' => 0, 'candidates' => 0, 'filled' => 0, 'unfilled' => 0];
        }

        $cycleId = $this->activeCycle->id;
        $total = Position::where('election_cycle_id', $cycleId)->count();
        $candidates = Candidate::where('election_cycle_id', $cycleId)->count();

        // Count positions na may approved candidates
        $filled = Position::where('election_cycle_id', $cycleId)->whereHas('candidates', fn($q) => $q->where('status', 'approved'))->count();

        return [
            'total' => $total,
            'candidates' => $candidates,
            'filled' => $filled,
            'unfilled' => $total - $filled,
        ];
    }

    /**
     * Fetch positions for the current cycle.
     */
    #[Computed]
    public function positions()
    {
        if (!$this->activeCycle) {
            return collect();
        }

        // Hinahatak ang positions base sa cycle ID (e.g., ID 1 mula sa DB mo).
        return Position::where('election_cycle_id', $this->activeCycle->id)->withCount('candidates')->orderBy('priority', 'asc')->get();
    }

    /**
     * Save or Update position.
     */
    public function savePosition()
    {
        if (!$this->activeCycle) {
            $this->dispatch('swal', title: 'Error', text: 'No election cycle found.', icon: 'error');
            return;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'max_winners' => 'required|integer|min:1',
            'priority' => 'required|integer|min:1',
        ]);

        Position::updateOrCreate(
            ['id' => $this->editingId],
            [
                'name' => $this->name,
                'max_winners' => $this->max_winners,
                'priority' => $this->priority,
                'election_cycle_id' => $this->activeCycle->id,
                'is_active' => true,
            ],
        );

        $this->reset(['name', 'description', 'max_winners', 'priority', 'editingId']);
        $this->dispatch('notify', message: 'Position saved successfully!', type: 'success');
    }

    public function editPosition(int $id)
    {
        $pos = Position::findOrFail($id);
        $this->editingId = $pos->id;
        $this->name = $pos->name;
        $this->max_winners = $pos->max_winners;
        $this->priority = $pos->priority;
    }

    public function deletePosition(int $id)
    {
        Position::destroy($id);
        $this->dispatch('notify', message: 'Position removed successfully!', type: 'success');
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
                <h2>Manage <span>Positions</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Add, edit, or remove election positions</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-glow btn-sm" data-bs-toggle="modal" data-bs-target="#addPositionModal"
                    wire:click="$set('editingId', null)">
                    <i class="bi bi-plus-lg me-1"></i>Add Position
                </button>
            </div>
        </div>

        {{-- Stats Row --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3 fade-in-up">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(56,142,60,0.15); color: var(--accent);"><i
                            class="bi bi-diagram-3-fill"></i></div>
                    <div class="stat-value" style="color: var(--accent);">{{ $this->stats['total'] }}</div>
                    <div class="stat-label">Total Positions</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(103,58,183,0.15); color: var(--purple);"><i
                            class="bi bi-people-fill"></i></div>
                    <div class="stat-value" style="color: var(--purple);">{{ $this->stats['candidates'] }}</div>
                    <div class="stat-label">Candidates</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(56,142,60,0.15); color: var(--success);"><i
                            class="bi bi-check-all"></i></div>
                    <div class="stat-value" style="color: var(--success);">{{ $this->stats['filled'] }}</div>
                    <div class="stat-label">Filled</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up">
                <div class="glass-card stat-card">
                    <div class="stat-icon" style="background: rgba(253,203,110,0.15); color: var(--warning);"><i
                            class="bi bi-exclamation-triangle-fill"></i></div>
                    <div class="stat-value" style="color: var(--warning);">{{ $this->stats['unfilled'] }}</div>
                    <div class="stat-label">Unfilled</div>
                </div>
            </div>
        </div>

        {{-- Position Hierarchy List --}}
        <div class="glass-card p-4 mb-4 fade-in-up">
            <h6 class="fw-bold mb-3"><i class="bi bi-list-nested me-2" style="color: var(--accent);"></i>Position
                Hierarchy</h6>

            @forelse($this->positions as $pos)
                <div class="position-card glass-card mb-3" wire:key="pos-{{ $pos->id }}">
                    <div class="position-icon" style="background: rgba(56,142,60,0.15); color: var(--accent);"><i
                            class="bi bi-award-fill"></i></div>
                    <div class="flex-grow-1">
                        <h6 class="fw-semibold mb-0">{{ $pos->name }}</h6>
                        <small class="text-white-50">Priority: {{ $pos->priority }} • Max Winners:
                            {{ $pos->max_winners }}</small>
                    </div>
                    <div class="position-count me-3" style="color: var(--accent);">{{ $pos->candidates_count }}
                        candidates</div>
                    <div class="d-flex gap-2">
                        <button class="action-btn action-btn-edit" wire:click="editPosition({{ $pos->id }})"
                            data-bs-toggle="modal" data-bs-target="#addPositionModal">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="action-btn action-btn-delete" wire:click="deletePosition({{ $pos->id }})"
                            wire:confirm="Are you sure you want to delete this position?">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            @empty
                <div class="text-center py-4 text-white-50">No positions found for the current cycle.</div>
            @endforelse
        </div>
    </main>

    {{-- Add/Edit Position Modal --}}
    <div class="modal fade modal-glass" id="addPositionModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-diagram-3-fill me-2" style="color: var(--accent);"></i>
                        {{ $editingId ? 'Edit Position' : 'Add Position' }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="savePosition">
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Position Title</label>
                            <input type="text" wire:model="name" class="form-control-glass w-100"
                                placeholder="e.g. Auditor">
                            @error('name')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label text-white-50 small">Max Winners</label>
                                <input type="number" wire:model="max_winners" class="form-control-glass w-100"
                                    min="1">
                            </div>
                            <div class="col-6">
                                <label class="form-label text-white-50 small">Priority Order</label>
                                <input type="number" wire:model="priority" class="form-control-glass w-100"
                                    min="1">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" wire:click="savePosition" class="btn btn-glow btn-sm"
                        data-bs-dismiss="modal">
                        {{ $editingId ? 'Update Position' : 'Save Position' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

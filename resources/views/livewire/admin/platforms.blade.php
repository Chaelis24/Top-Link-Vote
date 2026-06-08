<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Traits\AuthenticatesLogout;
use Livewire\Attributes\{Layout, Title};
use Illuminate\Support\Facades\{Auth, Session};
use App\Models\{Platform, Candidate, ElectionCycle};

new #[Layout('layouts.admin')] #[Title('Platform Management')] class extends Component {
    use WithPagination, AuthenticatesLogout;

    public string $search = '';
    public ?Platform $selectedPlatform = null;

    #[Computed]
    public function activeCycle()
    {
        return ElectionCycle::getActiveCycle();
    }

    public function getPlatformsProperty()
    {
        $activeCycleId = ElectionCycle::where('status', 'active')->value('id') ?? 0;

        return Platform::with(['candidate.student', 'candidate.position'])
            ->whereIn('platforms.id', function ($query) {
                $query->selectRaw('MAX(id)')->from('platforms')->groupBy('candidate_id');
            })
            ->join('candidates', 'platforms.candidate_id', '=', 'candidates.id')
            ->join('positions', 'candidates.position_id', '=', 'positions.id')
            ->where('candidates.election_cycle_id', $activeCycleId)
            ->whereNotNull('platforms.title')
            ->where('platforms.title', '!=', '')
            ->whereNotNull('platforms.agenda')
            ->where('platforms.agenda', '!=', '')
            ->where(function ($query) {
                $query
                    ->where('platforms.title', 'like', '%' . $this->search . '%')
                    ->orWhereHas('candidate.student', function ($q) {
                        $q->where('first_name', 'like', '%' . $this->search . '%')->orWhere('last_name', 'like', '%' . $this->search . '%');
                    })
                    ->orWhereHas('candidate', function ($q) {
                        $q->where('party_name', 'like', '%' . $this->search . '%');
                    });
            })
            ->select('platforms.*')
            ->orderByRaw("CASE WHEN platforms.status = 'pending' THEN 0 ELSE 1 END ASC")
            ->latest('platforms.created_at')
            ->paginate(10);
    }

    public function publishPlatform(int $id)
    {
        $platform = Platform::with('candidate')->findOrFail($id);

        if (empty($platform->title) || empty($platform->agenda)) {
            $this->dispatch('swal', [
                'title' => 'Action Denied!',
                'text' => 'Cannot approve platform with missing details.',
                'icon' => 'error',
            ]);
            return;
        }

        $platform->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $platform->candidate->update([
            'status' => 'approved',
        ]);

        $this->dispatch('swal', [
            'title' => 'Platform Published!',
            'text' => 'The candidate profile and platform are now live.',
            'icon' => 'success',
        ]);
    }

    public function rejectPlatform(int $id)
    {
        $platform = Platform::findOrFail($id);
        $platform->update([
            'status' => 'rejected',
            'approved_at' => null,
        ]);

        $this->dispatch('swal', [
            'title' => 'Platform Rejected',
            'text' => 'The platform status has been set to rejected.',
            'icon' => 'info',
        ]);
    }

    public function viewPlatform(int $id)
    {
        $this->selectedPlatform = Platform::with(['candidate.student', 'candidate.position'])->findOrFail($id);
        $this->dispatch('open-modal', id: 'viewPlatformModal');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function getAvatarColor($candidateId)
    {
        return '#3b82f6';
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
                <h2 class="fw-bold text-primary">Platform <span class="text-accent">Management</span></h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">View & Approve Candidate Platform</p>
            </div>
        </div>
        <div class="flex items-center w-full md:w-auto mb-3">
            <div class="search-wrap-modern relative w-full md:w-[300px]">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-muted"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or ID..."
                    class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none"
                    style="background-color: white; border: 1px solid #e5e7eb;">
            </div>
        </div>

        <div class="glass-card p-0 overflow-hidden border-0 shadow-sm">
            <div class="table-responsive hidden md:block">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="p-3">Candidate Name & Party Name</th>
                            <th class="p-3">Platform Title</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Submitted</th>
                            <th class="p-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="align-middle">
                        @forelse($this->platforms as $platform)
                            <tr wire:key="plt-desktop-{{ $platform->id }}">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="profile-avatar-sm shadow-sm text-white flex-shrink-0 d-flex align-items-center justify-content-center"
                                            style="background: {{ $this->getAvatarColor($platform->candidate->id) }}; ; width: 40px; height: 40px; border-radius: 24px; overflow: hidden;">
                                            @if ($platform->candidate?->photo)
                                                <img src="{{ asset('storage/' . $platform->candidate->photo) }}"
                                                    class="w-100 h-100 object-fit-cover rounded-circle">
                                            @else
                                                {{ strtoupper(substr($platform->candidate->student?->first_name ?? 'A', 0, 1)) }}{{ strtoupper(substr($platform->candidate->student?->last_name ?? '', 0, 1)) }}
                                            @endif
                                        </div>
                                        <div>
                                            <div class="fw-bold text-primary small">
                                                {{ $platform->candidate->student->first_name }}
                                                {{ $platform->candidate->student->last_name }}
                                            </div>
                                            <div class="text-muted tiny fw-semibold">
                                                {{ $platform->candidate->position->name }} | <span
                                                    class="text-accent">{{ $platform->candidate->party_name ?? 'No Party Name' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-dark small fw-bold mb-1">
                                        {{ $platform->title ?? 'No Platform Title' }}

                                        @if (empty($platform->title) || empty($platform->agenda))
                                            <i class="bi bi-exclamation-triangle-fill text-danger ms-1"
                                                title="Incomplete Platform Data"></i>
                                        @endif
                                    </div>
                                    <div class="text-muted tiny text-truncate" style="max-width: 250px;">
                                        {{ is_array($platform->agenda) ? implode(', ', $platform->agenda) : $platform->agenda ?? 'Please provide agenda details.' }}
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match ($platform->status) {
                                            'approved' => 'badge-approved',
                                            'rejected' => 'badge-rejected',
                                            default => 'badge-pending',
                                        };
                                    @endphp
                                    <span class="{{ $badgeClass }}">{{ ucfirst($platform->status) }}</span>
                                </td>
                                <td class="text-muted small">
                                    {{ $platform->created_at->format('M d, Y') }}
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">
                                        <x-icon-button variant="edit" wire:click="viewPlatform({{ $platform->id }})"
                                            title="Review Profile" size="30px" borderRadius="8px">
                                            <i class="bi bi-eye"></i>
                                        </x-icon-button>

                                        @if ($platform->status !== 'approved')
                                            <x-icon-button variant="approve" title="Approve" size="30px"
                                                borderRadius="8px" x-data
                                                @click="
                                                    Swal.fire({
                                                        title: 'Approve this manifesto?',
                                                        text: 'This will publish the candidate profile live.',
                                                        icon: 'question',
                                                        showCancelButton: true,
                                                        confirmButtonColor: 'var(--success-green, #198754)',
                                                        cancelButtonColor: '#6c757d',
                                                        confirmButtonText: 'Yes, Approve it!'
                                                    }).then((result) => {
                                                        if (result.isConfirmed) {
                                                            $wire.publishPlatform({{ $platform->id }})
                                                        }
                                                    })
                                                ">
                                                <i class="bi bi-check-lg"></i>
                                            </x-icon-button>
                                        @endif

                                        @if ($platform->status !== 'rejected')
                                            <x-icon-button variant="delete" title="Reject" size="30px"
                                                borderRadius="8px" x-data
                                                @click="
                                                    Swal.fire({
                                                        title: 'Reject this platform?',
                                                        text: 'This will mark the platform as rejected.',
                                                        icon: 'warning',
                                                        showCancelButton: true,
                                                        confirmButtonColor: 'var(--danger-red, #dc3545)',
                                                        cancelButtonColor: '#6c757d',
                                                        confirmButtonText: 'Yes, Reject it!'
                                                    }).then((result) => {
                                                        if (result.isConfirmed) {
                                                            $wire.rejectPlatform({{ $platform->id }})
                                                        }
                                                    })
                                                ">
                                                <i class="bi bi-x-lg"></i>
                                            </x-icon-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted small">No platform found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="block md:hidden">
                @forelse($this->platforms as $platform)
                    @php
                        $badgeClass = match ($platform->status) {
                            'approved' => 'badge-approved',
                            'rejected' => 'badge-rejected',
                            default => 'badge-pending',
                        };
                    @endphp
                    <div wire:key="plt-mobile-{{ $platform->id }}"
                        class="p-4 border-bottom flex flex-col gap-3 bg-white">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="profile-avatar-sm shadow-sm text-white flex-shrink-0 d-flex align-items-center justify-content-center"
                                    style="background: {{ $this->getAvatarColor($platform->candidate->id) }}; ; width: 40px; height: 40px; border-radius: 24px; overflow: hidden;">
                                    @if ($platform->candidate?->photo)
                                        <img src="{{ asset('storage/' . $platform->candidate->photo) }}"
                                            class="w-full h-full object-cover">
                                    @else
                                        {{ strtoupper(substr($platform->candidate->student?->first_name ?? 'A', 0, 1)) }}{{ strtoupper(substr($platform->candidate->student?->last_name ?? '', 0, 1)) }}======={{ strtoupper(substr($platform->candidate->student?->first_name ?? 'A', 0, 1)) }}{{ strtoupper(substr($candidate->student?->last_name ?? '', 0, 1)) }}>
                                    @endif
                                </div>
                                <div>
                                    <div class="fw-bold text-primary small">
                                        {{ $platform->candidate->student->first_name }}
                                        {{ $platform->candidate->student->last_name }}
                                    </div>
                                    <div class="text-muted tiny fw-semibold">
                                        {{ $platform->candidate->position->name }} | <span
                                            class="text-accent">{{ $platform->candidate->party_name ?? 'No Party Name' }}</span>
                                    </div>
                                </div>
                            </div>
                            <span class="{{ $badgeClass }}"
                                style="font-size: 0.7rem;">{{ ucfirst($platform->status) }}</span>
                        </div>
                        <div class="p-3 rounded-lg" style="background-color: #f8f9fa; border: 1px solid #edf2f7;">
                            <div class="text-dark small fw-bold mb-1">
                                {{ $platform->title ?? 'No Platform Title' }}
                                @if (empty($platform->title) || empty($platform->agenda))
                                    <i class="bi bi-exclamation-triangle-fill text-danger ms-1"></i>
                                @endif
                            </div>
                            <div class="text-muted tiny">
                                {{ is_array($platform->agenda) ? implode(', ', $platform->agenda) : $platform->agenda ?? 'No agenda details.' }}
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-1">
                            <div class="text-muted" style="font-size: 0.7rem;">
                                <i class="bi bi-calendar3 me-1"></i> {{ $platform->created_at->format('M d, Y') }}
                            </div>
                            <div class="flex gap-2">
                                <x-icon-button variant="edit" wire:click="viewPlatform({{ $platform->id }})">
                                    <i class="bi bi-eye"></i>
                                </x-icon-button>
                                @if ($platform->status !== 'approved')
                                    <x-icon-button variant="approve" x-data
                                        @click="
                                            Swal.fire({
                                                title: 'Approve this?',
                                                text: 'This platform will be published.',
                                                icon: 'question',
                                                showCancelButton: true,
                                                confirmButtonColor: 'var(--success-green, #198754)',
                                                cancelButtonColor: '#6c757d',
                                                confirmButtonText: 'Yes, approve it!'
                                            }).then((result) => {
                                                if (result.isConfirmed) {
                                                    $wire.publishPlatform({{ $platform->id }})
                                                }
                                            })
                                        ">
                                        <i class="bi bi-check-lg"></i>
                                    </x-icon-button>
                                @endif
                                @if ($platform->status !== 'rejected')
                                    <x-icon-button variant="delete" x-data
                                        @click="
                                            Swal.fire({
                                                title: 'Reject this?',
                                                text: 'This platform will be marked as rejected.',
                                                icon: 'warning',
                                                showCancelButton: true,
                                                confirmButtonColor: 'var(--danger-red, #dc3545)',
                                                cancelButtonColor: '#6c757d',
                                                confirmButtonText: 'Yes, reject it!'
                                            }).then((result) => {
                                                if (result.isConfirmed) {
                                                    $wire.rejectPlatform({{ $platform->id }})
                                                }
                                            })
                                        ">
                                        <i class="bi bi-x-lg"></i>
                                    </x-icon-button>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-5 text-center text-muted small">No records found.</div>
                @endforelse
            </div>

            <div class="custom-pagination">
                {{ $this->platforms->links('layouts.partials.custom-pagination') }}
            </div>
        </div>
    </main>

    <div class="modal fade" id="viewPlatformModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable px-2">
            <div class="modal-content border-0 shadow-lg">
                @if ($selectedPlatform)
                    <div class="modal-header bg-primary text-white p-2 p-md-3">
                        <h6 class="modal-title fw-bold small mb-0"><i class="bi bi-shield-check me-2"></i>Full
                            Candidate Review</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            style="font-size: 0.8rem;"></button>
                    </div>

                    <div class="modal-body p-0">
                        <div
                            class="p-3 p-md-4 bg-white border-bottom d-flex flex-column flex-md-row align-items-center gap-2 gap-md-4 text-center text-md-start">
                            <div class="profile-avatar-sm shadow-sm text-white flex-shrink-0 d-flex align-items-center justify-content-center"
                                style="background: {{ $this->getAvatarColor($platform->candidate->id) }}; ; width: 60px; height: 60px; border-radius: 50%; overflow: hidden;">
                                @if ($platform->candidate?->photo)
                                    <img src="{{ asset('storage/' . $platform->candidate->photo) }}"
                                        class="w-full h-full object-cover">
                                @else
                                    {{ strtoupper(substr($platform->candidate->student?->first_name ?? 'A', 0, 1)) }}{{ strtoupper(substr($platform->candidate->student?->last_name ?? '', 0, 1)) }}
                                @endif
                            </div>
                            <div>
                                <h5 class="fw-bold text-dark mb-1" style="font-size: 1.1rem;">
                                    {{ $selectedPlatform->candidate->student->first_name }}
                                    {{ $selectedPlatform->candidate->student->last_name }}
                                </h5>
                                <div
                                    class="d-flex flex-wrap justify-content-center justify-content-md-start gap-1 align-items-center">
                                    <span
                                        class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"
                                        style="font-size: 0.7rem;">{{ $selectedPlatform->candidate->position->name }}</span>
                                    <span class="text-muted fw-bold" style="font-size: 0.7rem;">Party:
                                        {{ $selectedPlatform->candidate->party_name ?? 'No Party' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 p-md-4">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <h6 class="fw-bold text-primary mb-2 text-uppercase"
                                        style="font-size: 0.7rem; letter-spacing: 0.5px;">Introductory Profile</h6>
                                    <div class="bg-light p-2 p-md-3 rounded-3 mb-2">
                                        <small class="d-block fw-bold text-primary" style="font-size: 0.65rem;">GWA /
                                            Average
                                            Grade</small>
                                        <span
                                            class="fw-bold text-muted small">{{ $selectedPlatform->candidate->average_grade ?? 'N/A' }}</span>
                                    </div>
                                    <div class="mb-2">
                                        <small class="d-block text-primary fw-bold"
                                            style="font-size: 0.85rem;">Achievements</small>
                                        <p class="text-dark mb-0" style="font-size: 0.80rem; line-height: 1.4;">
                                            {{ is_array($selectedPlatform->candidate->achievements) ? implode(', ', $selectedPlatform->candidate->achievements) : $selectedPlatform->candidate->achievements ?? 'None listed.' }}
                                        </p>
                                    </div>
                                    <div class="mb-0">
                                        <small class="d-block text-primary fw-bold" style="font-size: 0.85rem;">Past
                                            Positions</small>
                                        <ul class="ps-3 mb-0" style="font-size: 0.80rem;">
                                            @php
                                                $roles = is_array($selectedPlatform->candidate->previous_position)
                                                    ? $selectedPlatform->candidate->previous_position
                                                    : explode(
                                                        ',',
                                                        $selectedPlatform->candidate->previous_position ?? '',
                                                    );
                                                $roles = array_filter(array_map('trim', $roles));
                                            @endphp
                                            @forelse($roles as $role)
                                                <li>{{ is_array($role) ? implode(', ', $role) : $role }}</li>
                                            @empty
                                                <li>None</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                </div>

                                <div class="col-md-7 border-start-0 border-md-start">
                                    <div class="ps-md-4">
                                        <div class="mb-2">
                                            <small class="d-block text-muted fw-bold"
                                                style="font-size: 0.85rem;">Platform
                                                Title</small>
                                            <p class="fw-bold text-primary mb-2" style="font-size: 0.80rem;">
                                                {{ $selectedPlatform->title ?? 'No Title' }}</p>
                                        </div>
                                        <div class="mb-2">
                                            <small class="d-block text-muted fw-bold"
                                                style="font-size: 0.85rem;">Campaign
                                                Tagline</small>
                                            <p class="text-dark fst-italic mb-2" style="font-size: 0.80rem;">
                                                "{{ $selectedPlatform->tagline ?? 'No Tagline' }}"</p>
                                        </div>
                                        <div class="bg-primary-subtle p-2 p-md-3 rounded-3"
                                            style="max-height: 180px; overflow-y: auto;">
                                            <small class="d-block text-primary fw-bold mb-1"
                                                style="font-size: 0.85rem;">Agenda Details</small>
                                            <p class="text-dark mb-0"
                                                style="white-space: pre-line; font-size: 0.80rem; line-height: 1.4;">
                                                {{ is_array($selectedPlatform->agenda) ? implode("\n", $selectedPlatform->agenda) : $selectedPlatform->agenda ?? 'No Agenda' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer bg-light d-flex justify-content-end align-items-center">
                        <x-button type="button" variant="gray" data-bs-dismiss="modal"
                            style="height: 28px; width: 90px; font-size: 0.75rem; padding: 0 10px;">
                            Close
                        </x-button>
                        @if ($selectedPlatform && $selectedPlatform->status === 'pending')
                            <x-button type="button" variant="glow"
                                wire:click="publishPlatform({{ $selectedPlatform->id }})" data-bs-dismiss="modal"
                                wire:target="publishPlatform"
                                style="height: 28px; width: 90px; font-size: 0.75rem; padding: 0 10px;">

                                <span wire:loading.remove wire:target="publishPlatform">Approve</span>
                                <span wire:loading wire:target="publishPlatform">Approving...</span>
                            </x-button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('open-view-modal', event => {
            var myModal = new bootstrap.Modal(document.getElementById('viewPlatformModal'));
            myModal.show();
        });
    </script>
</div>

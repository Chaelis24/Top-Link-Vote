<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Url};
use Livewire\WithPagination;
use Illuminate\Support\Facades\{Auth, Session, DB};
use App\Models\{Candidate, Student, Platform};

new #[Layout('layouts.app')] #[Title('Manage Profiles')] class extends Component {
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    public $editingCandidateId;
    public $editForm = [
        'full_name' => '',
        'bio' => '',
    ];

    public function with(): array
    {
        return [
            'candidates' => Candidate::with(['student', 'position', 'platforms'])
                ->whereHas('student', function ($query) {
                    $query
                        ->where('first_name', 'like', '%' . $this->search . '%')
                        ->orWhere('last_name', 'like', '%' . $this->search . '%')
                        ->orWhere('student_id', 'like', '%' . $this->search . '%');
                })
                ->latest()
                ->paginate(10),
        ];
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function editProfile($id)
    {
        $candidate = Candidate::with(['student', 'platforms'])->findOrFail($id);
        $this->editingCandidateId = $id;

        $this->editForm = [
            'full_name' => $candidate->student->first_name . ' ' . $candidate->student->last_name,
            'bio' => $candidate->platform->vision ?? '',
        ];

        $this->dispatch('open-modal', id: 'editProfileModal');
    }

    public function updateProfile()
    {
        $candidate = Candidate::findOrFail($this->editingCandidateId);

        if ($candidate->platform) {
            $candidate->platform->update(['vision' => $this->editForm['bio']]);
        }

        $this->dispatch('close-modal', id: 'editProfileModal');
        $this->dispatch('notify', message: 'Profile updated successfully!', type: 'success');
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
    <style>
        /* Compact Action Buttons */
        .btn-action-sm {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            padding: 0;
            font-size: 1rem;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        /* Edit Profile Button (Green Bordered) */
        .btn-profile-edit {
            background: rgba(0, 184, 148, 0.1);
            border-color: var(--accent);
            color: var(--accent);
        }

        .btn-profile-edit:hover {
            background: var(--accent);
            color: white;
            box-shadow: 0 0 10px rgba(0, 184, 148, 0.3);
        }

        /* Platform Button (Purple Bordered) */
        .btn-platform-edit {
            background: rgba(162, 155, 254, 0.1);
            border-color: var(--purple);
            color: var(--purple);
        }

        .btn-platform-edit:hover {
            background: var(--purple);
            color: white;
            box-shadow: 0 0 10px rgba(162, 155, 254, 0.3);
        }

        .table-glass td {
            vertical-align: middle;
        }
    </style>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2>Manage <span>Profiles</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Review and edit candidate profile details</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="search-wrap" style="width: 250px;">
                    <i class="bi bi-search"></i>
                    <input type="text" wire:model.live.debounce.300ms="search" class="search-glass"
                        placeholder="Search profiles...">
                </div>
            </div>
        </div>

        <div class="glass-card p-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-glass mb-0">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Position / Party</th>
                            <th>Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($candidates as $candidate)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="profile-avatar-sm"
                                            style="width: 38px; height: 38px; background: var(--accent); color: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 0.85rem;">
                                            {{ substr($candidate->student->first_name, 0, 1) }}{{ substr($candidate->student->last_name, 0, 1) }}
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-white">{{ $candidate->student->first_name }}
                                                {{ $candidate->student->last_name }}</div>
                                            <div class="small text-white-50">{{ $candidate->student->course }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-white small fw-medium">{{ $candidate->position->name }}</div>
                                    <div class="text-accent small" style="font-size: 0.75rem;">
                                        {{ $candidate->party_name }}</div>
                                </td>
                                <td>
                                    @php
                                        $statusStyle =
                                            $candidate->status === 'pending'
                                                ? 'background: rgba(253,203,110,0.1); color: #fdcb6e; border: 1px solid rgba(253,203,110,0.2);'
                                                : 'background: rgba(0, 184, 148, 0.1); color: var(--accent); border: 1px solid rgba(0, 184, 148, 0.2);';
                                    @endphp
                                    <span class="badge"
                                        style="{{ $statusStyle }} font-weight: 500; padding: 0.4em 0.8em;">
                                        {{ ucfirst($candidate->status) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center align-items-center gap-2">
                                        {{-- Small Edit Profile Button --}}
                                        <button class="btn-action-sm btn-profile-edit"
                                            wire:click="editProfile({{ $candidate->id }})" title="Edit Profile">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        {{-- Small Platform Button --}}
                                        <button class="btn-action-sm btn-platform-edit" title="Edit Platform">
                                            <i class="bi bi-megaphone"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-white-50 p-5">
                                    <i class="bi bi-people mb-2 d-block" style="font-size: 2rem;"></i>
                                    No profiles found matching your search.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top border-white-10">
                {{ $candidates->links() }}
            </div>
        </div>
    </main>

    {{-- Edit Modal --}}
    <div class="modal fade modal-glass" id="editProfileModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content glass-card border-0">
                <div class="modal-header border-bottom border-white-10">
                    <h5 class="modal-title text-white"><i class="bi bi-person-gear me-2 text-accent"></i>Edit Profile
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form wire:submit="updateProfile">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label text-white-50 small">Candidate Name</label>
                                <input type="text" class="form-control-glass w-100" wire:model="editForm.full_name"
                                    readonly style="opacity: 0.7; cursor: not-allowed;">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-white-50 small">Platform / Visionary Statement</label>
                                <textarea class="form-control-glass w-100" rows="5" wire:model="editForm.bio"
                                    placeholder="Enter the candidate's platform or vision here..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top border-white-10">
                        <button type="button" class="btn btn-outline-light btn-sm px-3"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-glow btn-sm px-4">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.addEventListener('open-modal', event => {
            let el = document.getElementById(event.detail.id);
            bootstrap.Modal.getOrCreateInstance(el).show();
        });
        window.addEventListener('close-modal', event => {
            let el = document.getElementById(event.detail.id);
            let modal = bootstrap.Modal.getInstance(el);
            if (modal) modal.hide();
        });
    </script>
</div>

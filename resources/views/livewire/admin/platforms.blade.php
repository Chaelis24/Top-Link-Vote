<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\{Layout, Title};
use Illuminate\Support\Facades\{Auth, Session};
use App\Models\{Platform, Candidate};

new #[Layout('layouts.app')] #[Title('Platform Management')] class extends Component {
    use WithPagination;

    public string $search = '';

    /**
     * Reset pagination when searching to avoid "no results"
     * if the user is on a high page number.
     */
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function publishPlatform(int $id)
    {
        $platform = Platform::findOrFail($id);
        $platform->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $this->dispatch('swal', [
            'title' => 'Platform Published!',
            'text' => 'The candidate platform is now live.',
            'icon' => 'success',
        ]);
    }

    public function deletePlatform(int $id)
    {
        Platform::findOrFail($id)->delete();
        $this->dispatch('swal', [
            'title' => 'Deleted!',
            'text' => 'Platform record has been removed.',
            'icon' => 'warning',
        ]);
    }

    public function getPlatformsProperty()
    {
        return Platform::with(['candidate.student', 'candidate.position'])
            ->where(function ($query) {
                $query->where('title', 'like', '%' . $this->search . '%')->orWhereHas('candidate.student', function ($q) {
                    $q->where('first_name', 'like', '%' . $this->search . '%')->orWhere('last_name', 'like', '%' . $this->search . '%');
                });
            })
            ->orderByRaw("FIELD(status, 'pending') DESC")
            ->latest()
            ->paginate(10);
    }

    public function getStatusClass($status)
    {
        return match ($status) {
            'approved' => 'bg-success-subtle text-success border-success-subtle',
            'pending' => 'bg-warning-subtle text-warning border-warning-subtle',
            'rejected' => 'bg-danger-subtle text-danger border-danger-subtle',
            default => 'bg-secondary-subtle text-secondary',
        };
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
                <h2>Platform <span>Management</span></h2>
                <p class="text-white-50 mb-0">Review and moderate candidate manifestos</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="search-wrap" style="width: 300px;">
                    <i class="bi bi-search"></i>
                    <input type="text" wire:model.live.debounce.300ms="search" class="search-glass"
                        placeholder="Search title or candidate...">
                </div>
            </div>
        </div>

        <div class="glass-card overflow-hidden fade-in-up">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Platform Title</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->platforms as $platform)
                            <tr wire:key="plt-{{ $platform->id }}">
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm rounded-circle d-flex align-items-center justify-content-center fw-bold text-white shadow-sm"
                                            style="width: 35px; height: 35px; font-size: 0.8rem; background: var(--accent-color, #673ab7);">
                                            {{ strtoupper(substr($platform->candidate->student->first_name, 0, 1)) }}{{ strtoupper(substr($platform->candidate->student->last_name, 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="fw-bold text-white small">
                                                {{ $platform->candidate->student->first_name }}
                                                {{ $platform->candidate->student->last_name }}</div>
                                            <div class="text-white-50" style="font-size: 0.7rem;">
                                                {{ $platform->candidate->position->name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-white small fw-semibold">{{ $platform->title }}</div>
                                    <div class="text-white-50 tiny text-truncate" style="max-width: 250px;">
                                        {{ $platform->vision }}</div>
                                </td>
                                <td>
                                    <span
                                        class="badge border {{ $this->getStatusClass($platform->status) }} py-1 px-2 status-badge">
                                        {{ $platform->status }}
                                    </span>
                                </td>
                                <td class="text-white-50 small">
                                    {{ $platform->created_at->format('M d, Y') }}
                                </td>
                                <td class="text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button class="btn btn-outline-glow btn-sm" title="View Details"
                                            data-bs-toggle="modal" data-bs-target="#viewPlatform-{{ $platform->id }}">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        @if ($platform->status === 'pending')
                                            <button wire:click="publishPlatform({{ $platform->id }})"
                                                wire:confirm="Are you sure you want to approve and publish this platform?"
                                                class="btn btn-icon-approve btn-sm" title="Approve">
                                                <i class="bi bi-check-lg" wire:loading.remove
                                                    wire:target="publishPlatform({{ $platform->id }})"></i>
                                                <span class="spinner-border spinner-border-sm" wire:loading
                                                    wire:target="publishPlatform({{ $platform->id }})"></span>
                                            </button>
                                        @endif

                                        <button
                                            wire:confirm="Are you sure you want to delete this platform permanently?"
                                            wire:click="deletePlatform({{ $platform->id }})"
                                            class="btn btn-icon-danger btn-sm" title="Delete">
                                            <i class="bi bi-trash" wire:loading.remove
                                                wire:target="deletePlatform({{ $platform->id }})"></i>
                                            <span class="spinner-border spinner-border-sm" wire:loading
                                                wire:target="deletePlatform({{ $platform->id }})"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-white-50 small">No platforms found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top border-white-10">
                {{ $this->platforms->links() }}
            </div>
        </div>
    </main>

    @foreach ($this->platforms as $platform)
        <div class="modal fade" id="viewPlatform-{{ $platform->id }}" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content bg-dark text-white border-white-10 shadow-lg"
                    style="backdrop-filter: blur(15px); background: rgba(26, 34, 44, 0.95) !important;">
                    <div class="modal-header border-white-10">
                        <h5 class="modal-title fw-bold">Platform Review</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body custom-scrollbar">
                        <div class="mb-4">
                            <h4 class="text-accent mb-1">{{ $platform->title }}</h4>
                            <p class="text-white-50 small">Candidate: {{ $platform->candidate->student->first_name }}
                                {{ $platform->candidate->student->last_name }}</p>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 h-100"
                                    style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);">
                                    <h6 class="tiny fw-bold text-accent text-uppercase mb-3">
                                        <i class="bi bi-eye-fill me-2"></i>Vision
                                    </h6>
                                    <p class="small text-white-50 fst-italic mb-0" style="line-height: 1.6;">
                                        "{{ $platform->vision }}"
                                    </p>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 rounded-3 h-100"
                                    style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);">
                                    <h6 class="tiny fw-bold text-accent text-uppercase mb-3">
                                        <i class="bi bi-rocket-takeoff-fill me-2"></i>Mission
                                    </h6>
                                    <p class="small text-white-50 mb-0"
                                        style="white-space: pre-wrap; line-height: 1.6;">{{ $platform->mission }}</p>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="p-3 rounded-3 shadow-sm"
                                    style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);">
                                    <h6 class="tiny fw-bold text-accent text-uppercase mb-3">
                                        <i class="bi bi-target me-2"></i>Primary Goals
                                    </h6>

                                    @if (!empty($platform->goals) && count($platform->goals) > 0)
                                        <div class="row row-cols-1 row-cols-md-2 g-2">
                                            @foreach ($platform->goals as $goal)
                                                <div class="col">
                                                    <div class="d-flex align-items-start gap-2 small text-white-50">
                                                        <i class="bi bi-check2-circle text-accent mt-1"></i>
                                                        <span>{{ $goal }}</span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="small text-white-50 fst-italic mb-0">No specific goals provided.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="p-3 rounded-3 shadow-sm"
                                    style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05);">
                                    <h6 class="tiny fw-bold text-accent text-uppercase mb-3">
                                        <i class="bi bi-list-task me-2"></i>Action Plans & Roadmap
                                    </h6>

                                    @if (!empty($platform->action_plans) && count($platform->action_plans) > 0)
                                        <div class="timeline-simple">
                                            @foreach ($platform->action_plans as $index => $action_plan)
                                                <div class="d-flex gap-3 mb-3">
                                                    <div class="flex-shrink-0">
                                                        <span
                                                            class="badge rounded-circle bg-accent bg-opacity-25 text-accent d-flex align-items-center justify-content-center"
                                                            style="width: 24px; height: 24px; font-size: 0.7rem;">
                                                            {{ $index + 1 }}
                                                        </span>
                                                    </div>
                                                    <div class="small text-white-50 pt-1">
                                                        {{ $action_plan }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="small text-white-50 fst-italic mb-0">No specific action plans
                                            provided.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-white-10">
                        <button type="button" class="btn btn-outline-danger" data-bs-dismiss="modal">Close</button>
                        @if ($platform->status === 'pending')
                            <button wire:click="publishPlatform({{ $platform->id }})" class="btn btn-success"
                                data-bs-dismiss="modal">Approve & Publish</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <style>
        /* Table Glass Styling */
        .table-custom {
            --bs-table-bg: transparent;
            --bs-table-hover-bg: rgba(255, 255, 255, 0.03);
        }

        .table-custom thead th {
            background: rgba(0, 0, 0, 0.2);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 1.2rem 1rem;
            border: none;
        }

        .table-custom tbody tr {
            transition: all 0.2s ease;
        }

        .table-custom tbody tr:hover {
            background: rgba(255, 255, 255, 0.05) !important;
        }

        .table-custom tbody td {
            padding: 1.1rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Status Badge */
        .status-badge {
            font-size: 0.65rem;
            text-transform: uppercase;
        }

        /* ACTION BUTTONS */
        .btn-sm {
            width: 35px;
            height: 35px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* View Button (Cyan/Glass) */
        .btn-icon-glass {
            background: rgba(255, 255, 255, 0.05);
            color: #0dcaf0;
            border: 1px solid rgba(13, 202, 240, 0.3);
        }

        .btn-icon-glass:hover {
            background: #0dcaf0;
            color: #000;
            box-shadow: 0 0 15px rgba(13, 202, 240, 0.5);
        }

        /* Approve Button (Green) */
        .btn-icon-approve {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.4);
        }

        .btn-icon-approve:hover {
            background: #28a745;
            color: #fff;
            box-shadow: 0 0 15px rgba(40, 167, 69, 0.5);
        }

        /* Delete Button (Red) */
        .btn-icon-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.4);
        }

        .btn-icon-danger:hover {
            background: #dc3545;
            color: #fff;
            box-shadow: 0 0 15px rgba(220, 53, 69, 0.5);
        }

        /* Pagination Styles */
        .pagination {
            gap: 5px;
            margin-bottom: 0;
        }

        .page-link {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            border-radius: 8px !important;
        }

        .page-link:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .page-item.active .page-link {
            background: var(--accent-color, #673ab7);
            border-color: var(--accent-color, #673ab7);
        }
    </style>
</div>

<script>
    window.addEventListener('swal', event => {
        let data = event.detail[0] || event.detail;
        Swal.fire({
            title: data.title,
            text: data.text,
            icon: data.icon,
            background: "#1a222c",
            color: "#fff",
            confirmButtonColor: "var(--accent-color, #673ab7)"
        });
    });
</script>

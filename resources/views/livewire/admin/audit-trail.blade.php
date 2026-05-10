<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, On, Title};
use App\Models\ActivityLog;
use App\Models\User;
use Livewire\WithPagination;
use Illuminate\Support\Str;

new #[Layout('layouts.admin')] #[Title('User Activity')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterAction = '';
    public $filterCourse = '';

    public function with()
    {
        return [
            'logs' => ActivityLog::query()
                ->with(['user', 'student'])
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('description', 'like', '%' . $this->search . '%')
                            ->orWhere('action', 'like', '%' . $this->search . '%')
                            ->orWhere('student_id', 'like', '%' . $this->search . '%')
                            ->orWhereHas('student', function ($sq) {
                                $sq->where('first_name', 'like', '%' . $this->search . '%')->orWhere('last_name', 'like', '%' . $this->search . '%');
                            });
                    });
                })
                ->when($this->filterAction, fn($q) => $q->where('action', $this->filterAction))
                ->when($this->filterCourse, function ($q) {
                    $q->whereHas('student', function ($studentQuery) {
                        $studentQuery->where('course', $this->filterCourse);
                    });
                })
                ->latest()
                ->paginate(15),

            'actions' => cache()->remember('audit_actions', 60, fn() => ActivityLog::select('action')->distinct()->get()),

            'courses' => \App\Models\Student::select('course')->distinct()->whereNotNull('course')->pluck('course'),
        ];
    }

    #[On('echo-private:admin.audit-trail,AuditLogCreated')]
    public function refreshLogs() {}

    public function updatingFilterCourse()
    {
        $this->resetPage();
    }
    public function updatingSearch()
    {
        $this->resetPage();
    }
    public function updatingFilterAction()
    {
        $this->resetPage();
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
        return redirect()->route('login');
    }
}; ?>

<div>
    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2 class="fw-bold text-primary">System <span class="text-accent">Audit Trail</span></h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Detailed accountability and security monitoring</p>
            </div>
        </div>

        {{-- Filters Section --}}
        <div class="flex flex-wrap items-center gap-3 w-full mt-4">
            <div class="relative w-full md:w-80 order-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="bi bi-search text-gray-400"></i>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search activities..."
                    class="block w-full pl-10 pr-3 py-2.5 bg-white border-0 shadow-sm rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none h-[42px]">
            </div>

            <div class="flex gap-3 w-full md:w-auto order-2">
                <select wire:model.live="filterAction"
                    class="block px-3 py-2.5 bg-white border-0 shadow-sm rounded-xl text-sm cursor-pointer focus:ring-2 focus:ring-blue-500 h-[42px]">
                    <option value="">All Actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action->action }}">{{ $action->action }}</option>
                    @endforeach
                </select>

                <select wire:model.live="filterCourse"
                    class="block px-3 py-2.5 bg-white border-0 shadow-sm rounded-xl text-sm cursor-pointer focus:ring-2 focus:ring-blue-500 h-[42px]">
                    <option value="">All Courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course }}">{{ $course }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="glass-card p-0 border-0 shadow-sm mt-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 hidden md:table">
                    <thead class="bg-light">
                        <tr style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;">
                            <th class="ps-4">User / Student</th>
                            <th>Action & Description</th>
                            <th class="text-end pe-4">Location & Time</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.85rem;">
                        @forelse($logs as $log)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">
                                        {{ $log->student->student_id ?? ($log->student_id ?? 'ADMIN') }}</div>
                                    <div class="text-muted small">
                                        @if ($log->student)
                                            {{ $log->student->first_name }} {{ $log->student->last_name }}
                                        @else
                                            {{ $log->user->name ?? 'System User' }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match ($log->action) {
                                            'Voted', 'Approved' => 'bg-success-soft text-success',
                                            'Created', 'Added' => 'bg-info-soft text-info',
                                            'Update Candidate', 'Updated' => 'bg-warning-soft text-warning',
                                            'Deleted', 'Removed', 'Vote Failed' => 'bg-danger-soft text-danger',
                                            default => 'bg-primary-soft text-primary',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }} mb-1 px-2">{{ $log->action }}</span>
                                    <div class="text-muted x-small" style="max-width: 200px; line-height: 1.2;">
                                        {{ $log->description }}
                                    </div>
                                </td>
                                <td class="text-end pe-4">
                                    <div
                                        class="fw-bold small {{ str_contains($log->ip_address, 'Campus') ? 'text-success' : 'text-danger' }}">
                                        <i class="bi bi-geo-alt-fill me-1"></i>{{ $log->ip_address }}
                                    </div>
                                    <div class="text-muted x-small">{{ $log->created_at?->diffForHumans() }}</div>
                                    <div class="text-muted x-small" style="font-size: 0.65rem;">
                                        {{ $log->created_at?->format('M d, Y h:i A') }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted fst-italic">No activity logs
                                    found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                {{-- Mobile View Cards --}}
                <div class="md:hidden p-3 space-y-3">
                    @forelse($logs as $log)
                        <div class="border rounded-xl p-3 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-bold text-primary">{{ $log->student->student_id ?? 'ADMIN' }}</div>
                                    <div class="small fw-bold text-dark">
                                        {{ $log->student ? $log->student->first_name . ' ' . $log->student->last_name : $log->user->name ?? 'System' }}
                                    </div>
                                </div>
                                <span
                                    class="badge {{ $badgeClass ?? 'bg-primary-soft text-primary' }}">{{ $log->action }}</span>
                            </div>

                            <p class="text-muted small mb-2">{{ $log->description }}</p>

                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <span class="x-small text-muted"><i class="bi bi-geo-alt-fill text-danger"></i>
                                    {{ $log->ip_address }}</span>
                                <span class="x-small text-muted">{{ $log->created_at?->format('h:i A') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted">No activity logs found.</div>
                    @endforelse
                </div>
            </div>

            @if ($logs->hasPages())
                <div
                    class="p-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top bg-light">
                    <div class="text-muted small">
                        Showing <b>{{ $logs->firstItem() }}</b> to <b>{{ $logs->lastItem() }}</b> of
                        <b>{{ $logs->total() }}</b>
                    </div>
                    <div class="custom-pagination">
                        {{ $logs->links() }}
                    </div>
                </div>
            @endif
        </div>
    </main>

    <style>
        /* Soft Badges */
        .bg-success-soft {
            background-color: #d1e7dd;
            color: #0f5132;
        }

        .bg-warning-soft {
            background-color: #fff3cd;
            color: #664d03;
        }

        .bg-danger-soft {
            background-color: #f8d7da;
            color: #842029;
        }

        .bg-info-soft {
            background-color: #cff4fc;
            color: #055160;
        }

        .bg-primary-soft {
            background-color: #e2e3ff;
            color: #0d6efd;
        }

        .x-small {
            font-size: 0.7rem;
        }

        pre {
            white-space: pre-wrap;
            word-break: break-all;
            font-family: 'Courier New', Courier, monospace;
            background: transparent;
            padding: 0;
            color: #333;
        }

        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }

        .glass-card {
            border-radius: 15px;
            background: white;
        }
    </style>
</div>

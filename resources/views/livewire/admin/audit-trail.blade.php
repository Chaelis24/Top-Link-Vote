<?php

use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Traits\AuthenticatesLogout;
use Livewire\Attributes\{Layout, On, Title};
use App\Models\{ActivityLog, User, Course, Block};

new #[Layout('layouts.admin')] #[Title('User Activity')] class extends Component {
    use WithPagination, AuthenticatesLogout;

    public $search = '';
    public $filterAction = '';
    public $filterCourse = '';
    public $filterBlock = '';

    public function with()
    {
        return [
            'logs' => ActivityLog::query()
                ->with(['user', 'student.block.course'])
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
                    $q->whereHas('student.block.course', function ($sQuery) {
                        $sQuery->where('name', $this->filterCourse);
                    });
                })
                ->when($this->filterBlock, function ($q) {
                    $q->whereHas('student.block', function ($sQuery) {
                        $sQuery->where('id', $this->filterBlock);
                    });
                })
                ->latest()
                ->paginate(6),

            'actions' => cache()->remember('audit_actions', 60, fn() => ActivityLog::select('action')->distinct()->get()),
            'courses' => Course::select('name')->distinct()->whereNotNull('name')->pluck('name'),
            'blocks' => Block::all(),
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
    public function updatingFilterBlock()
    {
        $this->resetPage();
    }
}; ?>

<div>
    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2 class="fw-bold text-primary">System <span class="text-accent">Audit Trail</span></h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Track Student/Candidate Activity</p>
            </div>
        </div>

        <div class="flex flex-wrap justify-between gap-3 w-full mt-4">
            <div class="flex gap-3 w-full md:w-auto">
                <select wire:model.live="filterCourse"
                    class="block px-4 py-2 bg-white border-0 shadow-sm rounded-xl text-sm cursor-pointer focus:ring-2 focus:ring-blue-500 h-[42px]">
                    <option value="">All Courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course }}">{{ $course }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterBlock"
                    class="block px-4 py-2 bg-white border-0 shadow-sm rounded-xl text-sm cursor-pointer focus:ring-2 focus:ring-blue-500 h-[42px]">
                    <option value="">All Blocks</option>
                    @foreach ($blocks as $block)
                        <option value="{{ $block->id }}">
                            {{ $block->year_level }} - {{ $block->section }}
                        </option>
                    @endforeach
                </select>
                <select wire:model.live="filterAction"
                    class="block px-4 py-2 bg-white border-0 shadow-sm rounded-xl text-sm cursor-pointer focus:ring-2 focus:ring-blue-500 h-[42px]">
                    <option value="">All Actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action->action }}">{{ $action->action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="relative w-full md:w-80">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="bi bi-search text-gray-400"></i>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search activities..."
                    class="block w-full pl-10 pr-3 py-2.5 bg-white border-0 shadow-sm rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none h-[42px]">
            </div>
        </div>

        <div class="glass-card p-0 border-0 shadow-sm mt-3 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 hidden md:table">
                    <thead class="bg-light">
                        <tr style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 1px;">
                            <th class="ps-4">Student / Candidate</th>
                            <th>Action & Description</th>
                            <th class="text-end pe-4">IP Address & Time</th>
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

                <div class="md:hidden p-3 space-y-3">
                    @forelse($logs as $log)
                        @php
                            $badgeClass = match ($log->action) {
                                'Voted', 'Approved' => 'bg-success-soft text-success',
                                'Created', 'Added' => 'bg-info-soft text-info',
                                'Update Candidate', 'Updated' => 'bg-warning-soft text-warning',
                                'Deleted', 'Removed', 'Vote Failed' => 'bg-danger-soft text-danger',
                                default => 'bg-primary-soft text-primary',
                            };
                        @endphp
                        <div class="border rounded-xl p-3 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-bold text-primary">{{ $log->student->student_id ?? 'ADMIN' }}</div>
                                    <div class="small fw-bold text-dark">
                                        {{ $log->student ? $log->student->first_name . ' ' . $log->student->last_name : $log->user->name ?? 'System' }}
                                    </div>
                                </div>
                                <span
                                    class="badge {{ $badgeClass }}">{{ $log->action }}</span>
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

            <div class="custom-pagination">
                {{ $logs->links('layouts.partials.custom-pagination') }}
            </div>
        </div>
    </main>
</div>

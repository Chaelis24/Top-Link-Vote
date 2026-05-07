<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\ActivityLog;
use App\Models\User;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] #[Title('User Activity')] class extends Component {
    use WithPagination;

    public $search = '';
    public $filterAction = '';
    public $filterUser = '';

    public function with()
    {
        return [
            'logs' => ActivityLog::query()
                ->with(['user', 'student'])
                ->when($this->search, function ($query) {
                    $query->where(function ($q) {
                        $q->where('description', 'like', '%' . $this->search . '%')
                            ->orWhere('action', 'like', '%' . $this->search . '%')
                            ->orWhere('student_id', 'like', '%' . $this->search . '%');
                    });
                })
                ->when($this->filterAction, fn($q) => $q->where('action', $this->filterAction))
                ->when($this->filterUser, fn($q) => $q->where('user_id', $this->filterUser))
                ->latest()
                ->paginate(15),

            'actions' => ActivityLog::select('action')->distinct()->get(),

            'users' => User::whereIn('id', ActivityLog::distinct()->pluck('user_id'))->get(),
        ];
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
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Track user activities and system logs</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full">
            <div class="relative w-full md:w-80 order-1">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="bi bi-search text-gray-400"></i>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search activities..."
                    class="block w-full pl-10 pr-3 py-2.5 bg-white border-0 shadow-sm rounded-xl text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none h-[42px]">
            </div>

            <div class="flex gap-3 w-full md:w-auto order-2">
                <div class="flex-1 md:w-40">
                    <select wire:model.live="filterAction"
                        class="block w-full px-3 py-2.5 bg-white border-0 shadow-sm rounded-xl text-sm cursor-pointer focus:ring-2 focus:ring-blue-500 focus:outline-none h-[42px]">
                        <option value="">All Actions</option>
                        @foreach ($actions ?? [] as $action)
                            <option value="{{ $action->action }}">{{ $action->action }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex-1 md:w-40">
                    <select wire:model.live="filterUser"
                        class="block w-full px-3 py-2.5 bg-white border-0 shadow-sm rounded-xl text-sm cursor-pointer focus:ring-2 focus:ring-blue-500 focus:outline-none h-[42px]">
                        <option value="">All Users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="glass-card p-3 border-0 shadow-sm mt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle hidden md:table">
                    <thead class="table-light">
                        <tr style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Action</th>
                            <th>Reference No.</th>
                            <th>Timestamp</th>
                            <th class="text-end">IP Address</th>
                        </tr>
                    </thead>
                    <tbody style="font-size: 0.85rem;">
                        @forelse($logs as $log)
                            <tr>
                                <td class="fw-bold text-dark">
                                    {{ $log->student->student_id ?? ($log->student_id ?? 'N/A') }}
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="text-dark fw-semibold">
                                            @if ($log->student)
                                                {{ $log->student->first_name }} {{ $log->student->last_name }}
                                            @elseif ($log->user)
                                                {{ $log->user->first_name }} {{ $log->user->last_name }}
                                            @else
                                                <span class="text-muted fst-italic">System / Unknown</span>
                                            @endif
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $badgeClass = match ($log->action) {
                                            'Voted' => 'bg-success-soft text-success',
                                            'Vote Failed' => 'bg-danger-soft text-danger',
                                            default => 'bg-primary-soft text-primary',
                                        };
                                    @endphp
                                    <span class="badge px-2 py-1 {{ $badgeClass }}">
                                        {{ $log->action }}
                                    </span>
                                </td>
                                <td class="text-muted">
                                    {{ Str::contains($log->description, 'Reference No.')
                                        ? Str::before($log->description, '. Error:')
                                        : $log->description ?? '---' }}
                                </td>
                                <td class="text-muted">
                                    {{ $log->created_at?->format('M d, Y | h:i A') ?? 'No Timestamp' }}
                                </td>
                                <td class="text-end">
                                    <code class="text-muted" style="font-size: 0.75rem;">{{ $log->ip_address }}</code>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted fst-italic">
                                    No activity logs found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>

                <div class="md:hidden space-y-3">
                    @forelse($logs as $log)
                        <div class="border rounded-3 p-3 bg-white shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="fw-bold text-dark" style="font-size: 0.9rem;">
                                        {{ $log->student->student_id ?? ($log->student_id ?? 'N/A') }}
                                    </div>
                                    <div class="text-dark fw-semibold" style="font-size: 0.85rem;">
                                        @if ($log->student)
                                            {{ $log->student->first_name }} {{ $log->student->last_name }}
                                        @elseif ($log->user)
                                            {{ $log->user->first_name }} {{ $log->user->last_name }}
                                        @else
                                            <span class="text-muted fst-italic">System</span>
                                        @endif
                                    </div>
                                </div>
                                @php
                                    $badgeClass = match ($log->action) {
                                        'Voted' => 'bg-success-soft text-success',
                                        'Vote Failed' => 'bg-danger-soft text-danger',
                                        default => 'bg-primary-soft text-primary',
                                    };
                                @endphp
                                <span class="badge px-2 py-1 {{ $badgeClass }}">
                                    {{ $log->action }}
                                </span>
                            </div>

                            <div class="text-muted small mb-2 border-top pt-2">
                                <strong>Ref:</strong>
                                {{ Str::contains($log->description, 'Reference No.')
                                    ? Str::before($log->description, '. Error:')
                                    : $log->description ?? '---' }}
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                <span class="text-muted small">
                                    <i class="bi bi-clock me-1"></i>{{ $log->created_at?->format('M d, h:i A') }}
                                </span>
                                <code class="text-muted" style="font-size: 0.7rem;">{{ $log->ip_address }}</code>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted">No activity logs found.</div>
                    @endforelse
                </div>
            </div>

            @if ($logs->hasPages())
                <div class="mt-4 d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="text-muted small order-2 order-md-1">
                        Showing <b>{{ $logs->firstItem() }}</b> to <b>{{ $logs->lastItem() }}</b> of
                        <b>{{ $logs->total() }}</b> entries
                    </div>
                    <div class="order-1 order-md-2">
                        {{ $logs->links() }}
                    </div>
                </div>
            @endif
        </div>
    </main>
</div>

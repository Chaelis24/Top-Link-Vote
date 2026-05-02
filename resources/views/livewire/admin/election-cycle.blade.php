<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Computed};
use Illuminate\Support\Facades\{Auth, Session, DB};
use Illuminate\Support\Str;
use App\Models\{ElectionCycle, Setting};

new #[Layout('layouts.admin')] #[Title('Election Cycle')] class extends Component {
    public bool $allowVoting = false;
    public bool $showResults = false;
    public bool $showProfiles = false;
    public bool $lockChanges = false;
    public bool $maintenanceMode = false;

    public int $progress = 0;
    public string $currentCycle = 'No Active Cycle';
    public string $status = 'inactive';
    public $startTime = null;
    public $endTime = null;

    public string $cycle_name = '';
    public string $academic_year = '';
    public string $start_date = '';
    public string $end_date = '';
    public string $filing_start = '';
    public string $filing_end = '';
    public string $campaign_start = '';
    public string $campaign_end = '';
    public string $results_date = '';

    public $fStartIso = '',
        $fEndIso = '',
        $cStartIso = '',
        $cEndIso = '',
        $vStartIso = '',
        $vEndIso = '',
        $rDateIso = '';

    #[Computed]
    public function active()
    {
        return ElectionCycle::where('status', 'active')->latest()->first();
    }

    public function mount()
    {
        $active = $this->active;

        if ($active) {
            $this->currentCycle = $active->name;
            $this->status = $active->status;
            $this->academic_year = $active->academic_year;

            $this->start_date = optional($active->voting_start)->format('Y-m-d\TH:i') ?? '';
            $this->end_date = optional($active->voting_end)->format('Y-m-d\TH:i') ?? '';
            $this->filing_start = optional($active->filing_start)->format('Y-m-d') ?? '';
            $this->filing_end = optional($active->filing_end)->format('Y-m-d') ?? '';

            $this->fStartIso = optional($active->filing_start)->toIso8601String() ?? '';
            $this->fEndIso = optional($active->filing_end)->toIso8601String() ?? '';
            $this->cStartIso = optional($active->campaign_start)->toIso8601String() ?? '';
            $this->cEndIso = optional($active->campaign_end)->toIso8601String() ?? '';
            $this->vStartIso = optional($active->voting_start)->toIso8601String() ?? '';
            $this->vEndIso = optional($active->voting_end)->toIso8601String() ?? '';
            $this->rDateIso = optional($active->results_date)->toIso8601String() ?? '';

            $this->startTime = $this->vStartIso;
            $this->endTime = $this->vEndIso;

            $this->campaign_start = optional($active->campaign_start)->format('Y-m-d') ?? '';
            $this->campaign_end = optional($active->campaign_end)->format('Y-m-d') ?? '';
            $this->results_date = optional($active->results_date)->format('Y-m-d\TH:i') ?? '';

            if ($active->voting_start && $active->voting_end) {
                $start = $active->voting_start;
                $end = $active->voting_end;
                $now = now();

                if ($now->lt($start)) {
                    $this->progress = 0;
                } elseif ($now->gt($end)) {
                    $this->progress = 100;
                } else {
                    $total = $start->diffInSeconds($end);
                    $elapsed = $start->diffInSeconds($now);
                    $this->progress = (int) (($elapsed / max($total, 1)) * 100);
                }
            }
        }

        $settings = Setting::pluck('value', 'key')->toArray();
        $this->allowVoting = (bool) ($settings['allowVoting'] ?? false);
        $this->showResults = (bool) ($settings['showResults'] ?? false);
        $this->showProfiles = (bool) ($settings['showProfiles'] ?? false);
        $this->lockChanges = (bool) ($settings['lockChanges'] ?? false);
        $this->maintenanceMode = (bool) ($settings['maintenanceMode'] ?? false);
    }

    public function toggleSetting(string $setting)
    {
        if (property_exists($this, $setting)) {
            if ($setting === 'allowVoting' && !$this->allowVoting) {
                $active = $this->active;
                $now = now();

                if (!$active || $now->lt($active->voting_start)) {
                    $this->dispatch('swal', [
                        'title' => 'Access Restricted',
                        'text' => 'Voting cannot be enabled during the Filing or Campaign periods. Please wait for the scheduled voting start date.',
                        'icon' => 'error',
                    ]);
                    return;
                }
            }

            $this->{$setting} = !$this->{$setting};
            Setting::updateOrCreate(['key' => $setting], ['value' => $this->{$setting}]);

            $this->dispatch('swal', [
                'title' => 'Setting Updated',
                'text' => 'The election configuration has been successfully changed.',
                'icon' => 'success',
            ]);
        }
    }

    public function createNewCycle()
    {
        $this->validate([
            'cycle_name' => 'required|min:5',
            'academic_year' => 'required|string|size:9|regex:/^\d{4}-\d{4}$/',
            'filing_start' => 'required|date',
            'filing_end' => 'required|date|after_or_equal:filing_start',
            'campaign_start' => 'required|date|after:filing_end',
            'campaign_end' => 'required|date|after:campaign_start',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'results_date' => 'required|date|after:end_date',
        ]);

        DB::transaction(function () {
            ElectionCycle::where('status', 'active')->update(['status' => 'completed']);

            ElectionCycle::create([
                'name' => $this->cycle_name,
                'academic_year' => $this->academic_year,
                'filing_start' => $this->filing_start,
                'filing_end' => $this->filing_end,
                'campaign_start' => $this->campaign_start,
                'campaign_end' => $this->campaign_end,
                'voting_start' => $this->start_date,
                'voting_end' => $this->end_date,
                'results_date' => $this->results_date,
                'status' => 'active',
            ]);
        });

        $this->dispatch('swal', [
            'title' => 'Cycle Initialized!',
            'text' => 'New election cycle is now active.',
            'icon' => 'success',
        ]);

        return $this->redirect('/admin/election-cycle', navigate: true);
    }

    public function updateDates()
    {
        $this->validate([
            'filing_start' => 'required|date',
            'filing_end' => 'required|date',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $active = $this->active;
        if ($active) {
            $active->update([
                'filing_start' => $this->filing_start,
                'filing_end' => $this->filing_end,
                'voting_start' => $this->start_date,
                'voting_end' => $this->end_date,
            ]);
        }

        $this->dispatch('swal', [
            'title' => 'Date Updated!',
            'text' => 'Election cycle date is now updated.',
            'icon' => 'success',
        ]);

        return $this->redirect('/admin/election-cycle', navigate: true);
    }

    public function endElection()
    {
        $active = $this->active;
        if ($active) {
            $active->update(['status' => 'completed']);
        }

        $this->dispatch('swal', [
            'title' => 'Election Ended Successfully!',
            'text' => 'Election cycle is now ended.',
            'icon' => 'success',
        ]);

        return $this->redirect('/admin/election-cycle', navigate: true);
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
        <div class="topbar" wire:key="cycle-topbar">
            <div>
                <h2 class="fw-bold text-primary">Election <span class="text-accent">Cycle</span></h2>
                <p class="text-muted mb-0 small">Configure election dates and live system controls</p>
            </div>
            <button class="btn-glow d-flex align-items-center justify-content-center gap-1" data-bs-toggle="modal"
                data-bs-target="#newCycleModal" style="height: 38px; min-width: 38px; border-radius: 8px;"
                title="New Election Cycle">
                <i class="bi bi-plus-lg"></i>
                <span class="hidden md:block">New Election Cycle</span>
            </button>
        </div>

        <div class="row g-4" x-data="{
            now: new Date().getTime(),
            dates: {
                fS: '{{ $fStartIso }}' ? new Date('{{ $fStartIso }}').getTime() : null,
                fE: '{{ $fEndIso }}' ? new Date('{{ $fEndIso }}').getTime() : null,
                cS: '{{ $cStartIso }}' ? new Date('{{ $cStartIso }}').getTime() : null,
                cE: '{{ $cEndIso }}' ? new Date('{{ $cEndIso }}').getTime() : null,
                vS: '{{ $vStartIso }}' ? new Date('{{ $vStartIso }}').getTime() : null,
                vE: '{{ $vEndIso }}' ? new Date('{{ $vEndIso }}').getTime() : null,
                rD: '{{ $rDateIso }}' ? new Date('{{ $rDateIso }}').getTime() : null
            },
            getRemaining(target) {
                if (!target) return { d: 0, h: 0, m: 0, s: 0, closed: true };
                let diff = target - this.now;
                if (diff <= 0) return { d: 0, h: 0, m: 0, s: 0, closed: true };
                return {
                    d: Math.floor(diff / (1000 * 60 * 60 * 24)),
                    h: Math.floor((diff / (1000 * 60 * 60)) % 24),
                    m: Math.floor((diff / 1000 / 60) % 60),
                    s: Math.floor((diff / 1000) % 60),
                    closed: false
                };
            },
            pad(n) { return String(n).padStart(2, '0') },
            init() { setInterval(() => { this.now = new Date().getTime() }, 1000) }
        }">

            <div class="col-lg-8">
                <div
                    class="glass-card p-3 p-md-4 border-0 shadow-sm bg-white overflow-hidden position-relative mb-3 mb-md-4">

                    <div class="d-flex justify-content-between align-items-start mb-3 mb-md-4">
                        <div>
                            <h5 class="fw-bold text-primary mb-1 h4-md">{{ $currentCycle }}</h5>
                            <p class="text-muted text-[11px] md:text-sm mb-0">Academic Year :
                                {{ $academic_year ?? 'Not Set' }}</p>
                        </div>
                        <span
                            class="badge d-flex align-items-center gap-1 gap-md-2 px-2 py-1 px-md-3 py-2 rounded-pill shadow-sm"
                            style="font-size: 0.75rem;"
                            :class="!dates.vE ? 'bg-secondary' : (now < dates.vE ?
                                'bg-success-subtle text-success border border-success' :
                                'bg-danger-subtle text-danger border border-danger')">
                            <span x-show="dates.vE" class="pulse-dot" style="width: 6px; height: 6px;"
                                :style="'background: ' + (now < dates.vE ? '#10b981' : '#ef4444')"></span>
                            <span class="fw-bold"
                                x-text="!dates.vE ? 'No Cycle' : (now < dates.vE ? 'Active' : 'Closed')"></span>
                        </span>
                    </div>

                    @if ($this->active)
                        <div class="vstack gap-2 gap-md-3 mt-2 mt-md-4">
                            @php
                                $phases = [
                                    [
                                        'id' => 'f',
                                        'title' => 'Phase 1: Filing Period',
                                        'start' => 'fS',
                                        'end' => 'fE',
                                        'color' => 'text-danger',
                                    ],
                                    [
                                        'id' => 'c',
                                        'title' => 'Phase 2: Campaign Period',
                                        'start' => 'cS',
                                        'end' => 'cE',
                                        'color' => 'text-warning',
                                    ],
                                    [
                                        'id' => 'v',
                                        'title' => 'Phase 3: Voting Period',
                                        'start' => 'vS',
                                        'end' => 'vE',
                                        'color' => 'text-success',
                                    ],
                                    [
                                        'id' => 'r',
                                        'title' => 'Final: Results Proclamation',
                                        'start' => 'rD',
                                        'end' => null,
                                        'color' => 'text-accent',
                                    ],
                                ];
                            @endphp

                            @foreach ($phases as $phase)
                                <div class="p-2 p-md-3 border rounded-3 timeline-box shadow-sm"
                                    :class="now >= dates.{{ $phase['start'] }} && (!dates.{{ $phase['end'] ?? 'null' }} ||
                                        now < dates.{{ $phase['end'] ?? 'null' }}) ? 'active' : ''">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1 overflow-hidden">
                                            <h6 class="fw-bold mb-0 uppercase text-[10px] md:text-xs text-muted">
                                                {{ $phase['title'] }}</h6>

                                            @if ($phase['end'])
                                                <template x-if="now < dates.{{ $phase['start'] }}">
                                                    <div class="fw-bold text-primary mt-1 text-[11px] md:text-sm">Starts
                                                        in: <span
                                                            x-text="pad(getRemaining(dates.{{ $phase['start'] }}).d) + 'd ' + pad(getRemaining(dates.{{ $phase['start'] }}).h) + 'h'"></span>
                                                    </div>
                                                </template>
                                                <template
                                                    x-if="now >= dates.{{ $phase['start'] }} && now < dates.{{ $phase['end'] }}">
                                                    <div
                                                        class="fw-bold {{ $phase['color'] }} mt-1 text-[11px] md:text-sm">
                                                        Ends in: <span
                                                            x-text="pad(getRemaining(dates.{{ $phase['end'] }}).d) + 'd ' + pad(getRemaining(dates.{{ $phase['end'] }}).h) + 'h ' + pad(getRemaining(dates.{{ $phase['end'] }}).m) + 'm'"></span>
                                                    </div>
                                                </template>
                                                <template x-if="now >= dates.{{ $phase['end'] }}">
                                                    <div
                                                        class="fw-bold text-muted mt-1 text-[11px] md:text-sm text-truncate">
                                                        <i class="bi bi-check-circle-fill me-1 text-success"></i>Closed
                                                    </div>
                                                </template>
                                            @else
                                                <template x-if="now < dates.rD">
                                                    <div class="fw-bold text-primary mt-1 text-[11px] md:text-sm">
                                                        Scheduled: <span
                                                            x-text="pad(getRemaining(dates.rD).d) + 'd ' + pad(getRemaining(dates.rD).h) + 'h'"></span>
                                                    </div>
                                                </template>
                                                <template x-if="now >= dates.rD">
                                                    <div class="fw-bold text-accent mt-1 text-[11px] md:text-sm">
                                                        <i class="bi bi-megaphone-fill me-1"></i>Results Released
                                                    </div>
                                                </template>
                                            @endif
                                        </div>
                                        <span class="badge rounded-pill" style="font-size: 0.65rem;"
                                            :class="now < dates.{{ $phase['start'] }} ? 'bg-secondary' : (
                                                {{ $phase['end'] ? 'now < dates.' . $phase['end'] : 'true' }} ?
                                                '{{ $phase['id'] == 'r' ? 'bg-primary' : ($phase['id'] == 'v' ? 'bg-success' : 'bg-warning') }}' :
                                                'bg-success')">
                                            <span
                                                x-text="now < dates.{{ $phase['start'] }} ? 'Upcoming' : ({{ $phase['end'] ? 'now < dates.' . $phase['end'] : 'true' }} ? '{{ $phase['id'] == 'r' ? 'Released' : 'Ongoing' }}' : 'Done')"></span>
                                        </span>
                                    </div>

                                    @if ($phase['id'] == 'v')
                                        <div class="progress-container mt-2" x-show="now >= dates.vS && now < dates.vE">
                                            <div class="progress-bar-fill"
                                                style="width: {{ $progress }}%; height: 6px;"></div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                        </div>
                    @else
                        <div class="text-center py-5">
                            <h6 class="text-muted">Set up an election cycle to see the timeline.</h6>
                        </div>
                    @endif
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Pinaliit ang padding sa mobile (p-3 p-md-4) -->
                <div class="glass-card p-3 p-md-4 border-0 shadow-sm bg-white h-100">
                    <!-- Mas maliit na margin bottom (mb-3) -->
                    <h6 class="fw-bold text-primary mb-3 mb-md-4 uppercase small">
                        <i class="bi bi-sliders me-2"></i>Live Controls
                    </h6>

                    @foreach ([['key' => 'allowVoting', 'label' => 'Allow Voting', 'desc' => 'Open the voting portal for students'], ['key' => 'showResults', 'label' => 'Show Election Results', 'desc' => 'Display the tally to the student dashboard'], ['key' => 'showProfiles', 'label' => 'Show Candidate Profiles', 'desc' => 'Display bios and platforms'], ['key' => 'lockChanges', 'label' => 'Lock Profile Editing', 'desc' => 'Prevent candidates from updating their info'], ['key' => 'maintenanceMode', 'label' => 'Emergency Pause', 'desc' => 'Instantly freeze all site activity']] as $setting)
                        <div
                            class="control-item d-flex justify-content-between align-items-center mb-2 mb-md-3 p-2 rounded-3 hover-bg-light border shadow-xs">
                            <div class="pe-2">
                                <!-- Pinaliit ang label sa mobile (text-[13px]) -->
                                <div class="fw-bold text-dark text-[13px] md:text-sm">{{ $setting['label'] }}</div>
                                <!-- Pinaliit ang description text (text-[10px]) -->
                                <div class="text-muted text-[10px] md:text-xs leading-tight">
                                    @if ($setting['key'] == 'allowVoting')
                                        <span :class="now < dates.vS ? 'text-danger fw-bold' : 'text-muted'">
                                            <i class="bi bi-clock me-1"></i>
                                            <span
                                                x-text="now < dates.vS ? 'Auto-scheduled' : '{{ $setting['desc'] }}'"></span>
                                        </span>
                                    @else
                                        {{ $setting['desc'] }}
                                    @endif
                                </div>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" style="cursor: pointer;"
                                    @click.prevent="
                            const isVotingSetting = '{{ $setting['key'] }}' === 'allowVoting';
                            const isNotYetVotingPeriod = {{ !$this->active || now()->lt($this->active->voting_start) ? 'true' : 'false' }};
                            const isTurningOn = !{{ $this->{$setting['key']} ? 'true' : 'false' }};

                            if (isVotingSetting && isNotYetVotingPeriod && isTurningOn) {
                                Swal.fire({
                                    title: 'Access Restricted',
                                    text: 'Voting cannot be enabled during the Filing or Campaign periods. Please wait for the scheduled start date.',
                                    icon: 'error',
                                    confirmButtonColor: '#3085d6'
                                });
                            } else {
                                Swal.fire({
                                    title: 'Confirm Action',
                                    text: 'Are you sure you want to toggle {{ $setting['label'] }}?',
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: 'Yes, proceed!'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        $wire.toggleSetting('{{ $setting['key'] }}')
                                    }
                                });
                            }
                        "
                                    {{ $this->{$setting['key']} ? 'checked' : '' }}>
                            </div>
                        </div>
                    @endforeach

                    <!-- Action Buttons: Mas compact na spacing -->
                    <div class="mt-3 mt-md-4 pt-3 border-top d-flex flex-column gap-2">
                        <button class="btn btn-outline-primary btn-sm w-100 py-2 fw-bold text-[12px] md:text-sm"
                            @click.prevent="
                    Swal.fire({
                        title: 'Update Election Dates?',
                        text: 'Make sure the dates are correct before saving the changes.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, Update now'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $wire.updateDates()
                        }
                    })
                ">
                            <i class="bi bi-calendar-check me-2"></i>Update Cycle Dates
                        </button>
                        <button class="btn btn-outline-danger btn-sm w-100 py-2 fw-bold text-[12px] md:text-sm"
                            @click.prevent="
                    Swal.fire({
                        title: 'Final Warning',
                        text: 'This will end the current election cycle. This action cannot be undone!',
                        icon: 'warning',
                        showCancelButton: true,
                        cancelButtonColor: '#d33',
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'Yes, End it now'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $wire.endElection()
                        }
                    })
                ">
                            <i class="bi bi-stop-circle me-2"></i>End Election
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <div class="modal fade" id="newCycleModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg px-2">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white p-3 p-md-4">
                    <h6 class="modal-title fw-bold small mb-0">New Election Cycle</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        style="font-size: 0.8rem;"></button>
                </div>
                <form wire:submit.prevent="createNewCycle">
                    <div class="modal-body p-3 p-md-4">
                        <div class="row g-2 g-md-3 mb-2 mb-md-3">
                            <div class="col-md-8">
                                <label class="fw-bold text-muted uppercase"
                                    style="font-size: 0.65rem; letter-spacing: 0.5px;">Cycle Name</label>
                                <input type="text" wire:model="cycle_name" class="form-control-modern py-1 py-md-2"
                                    placeholder="e.g. CSG General Election" style="font-size: 0.85rem;">
                                @error('cycle_name')
                                    <small class="text-danger" style="font-size: 0.7rem;">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold text-muted uppercase"
                                    style="font-size: 0.65rem; letter-spacing: 0.5px;">Academic Year</label>
                                <input type="text" wire:model="academic_year"
                                    class="form-control-modern py-1 py-md-2" placeholder="2026-2027" maxlength="9"
                                    style="font-size: 0.85rem;">
                                @error('academic_year')
                                    <small class="text-danger" style="font-size: 0.7rem;">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                        <div class="row g-2 g-md-3 mb-2 mb-md-3">
                            <div class="col-6">
                                <label class="fw-bold text-danger uppercase" style="font-size: 0.65rem;">Filing
                                    Start</label>
                                <input type="date" wire:model="filing_start"
                                    class="form-control-modern py-1 py-md-2" style="font-size: 0.85rem;">
                            </div>
                            <div class="col-6">
                                <label class="fw-bold text-danger uppercase" style="font-size: 0.65rem;">Filing
                                    End</label>
                                <input type="date" wire:model="filing_end"
                                    class="form-control-modern py-1 py-md-2" style="font-size: 0.85rem;">
                            </div>
                        </div>
                        <div class="row g-2 g-md-3 mb-2 mb-md-3">
                            <div class="col-6">
                                <label class="fw-bold text-warning uppercase" style="font-size: 0.65rem;">Campaign
                                    Start</label>
                                <input type="date" wire:model="campaign_start"
                                    class="form-control-modern py-1 py-md-2" style="font-size: 0.85rem;">
                            </div>
                            <div class="col-6">
                                <label class="fw-bold text-warning uppercase" style="font-size: 0.65rem;">Campaign
                                    End</label>
                                <input type="date" wire:model="campaign_end"
                                    class="form-control-modern py-1 py-md-2" style="font-size: 0.85rem;">
                            </div>
                        </div>
                        <div class="row g-2 g-md-3 mb-2 mb-md-3">
                            <div class="col-6">
                                <label class="fw-bold text-primary uppercase" style="font-size: 0.65rem;">Voting
                                    Start</label>
                                <input type="datetime-local" wire:model="start_date"
                                    class="form-control-modern py-1 py-md-2" style="font-size: 0.85rem;">
                            </div>
                            <div class="col-6">
                                <label class="fw-bold text-primary uppercase" style="font-size: 0.65rem;">Voting
                                    End</label>
                                <input type="datetime-local" wire:model="end_date"
                                    class="form-control-modern py-1 py-md-2" style="font-size: 0.85rem;">
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="fw-bold text-success uppercase" style="font-size: 0.65rem;">Results
                                Proclamation Date</label>
                            <input type="datetime-local" wire:model="results_date"
                                class="form-control-modern py-1 py-md-2" style="font-size: 0.85rem;">
                            @error('results_date')
                                <small class="text-danger" style="font-size: 0.7rem;">{{ $message }}</small>
                            @enderror
                        </div>
                    </div>

                    <div class="modal-footer bg-light p-2 p-md-3">
                        <button type="submit" class="btn btn-primary rounded-pill w-100 w-md-auto py-2 px-4"
                            style="font-size: 0.85rem;"
                            @click.prevent="
                                Swal.fire({
                                    title: 'Initialize New Cycle?',
                                    text: 'This will set the current active cycle to completed and start this new one.',
                                    icon: 'question',
                                    showCancelButton: true,
                                    confirmButtonColor: '#0d6efd',
                                    cancelButtonColor: '#6c757d',
                                    confirmButtonText: 'Yes, Initialize'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        $wire.createNewCycle()
                                    }
                                })
                            ">
                            Initialize Cycle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

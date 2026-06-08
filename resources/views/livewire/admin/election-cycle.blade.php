<?php

use Illuminate\Support\Str;
use Livewire\Volt\Component;
use App\Traits\AuthenticatesLogout;
use App\Models\{ElectionCycle, Setting, Student};
use App\Http\Requests\Admin\ElectionCycleRequest;
use Livewire\Attributes\{Layout, Title, Computed};
use Illuminate\Support\Facades\{Auth, Session, DB, Cache};

new #[Layout('layouts.admin')] #[Title('Election Cycle')] class extends Component {
    use AuthenticatesLogout;

    public bool $allowVoting = false;
    public bool $showResults = false;
    public bool $lockChanges = false;
    public bool $maintenanceMode = false;

    public int $progress = 0;
    public int $filingProgress = 0;
    public int $campaignProgress = 0;

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
        return ElectionCycle::getActiveCycle();
    }

    public function with(): array
    {
        $active = ElectionCycle::getActiveCycle();
        return [
            'active' => $active,
            'vEndIso' => $active?->voting_end?->format('Y-m-d\TH:i:s'),
        ];
    }

    public function mount()
    {
        $active = $this->active;
        $settings = Setting::pluck('value', 'key')->toArray();

        if ($active = $this->active) {
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

            $this->startTime = $this->vStartIso ?: now()->toIso8601String();
            $this->endTime = $this->vEndIso ?: now()->toIso8601String();

            $this->campaign_start = optional($active->campaign_start)->format('Y-m-d') ?? '';
            $this->campaign_end = optional($active->campaign_end)->format('Y-m-d') ?? '';
            $this->results_date = optional($active->results_date)->format('Y-m-d\TH:i') ?? '';

            $this->updateProgress();
        } else {
            $this->startTime = now()->toIso8601String();
            $this->endTime = now()->toIso8601String();
        }

        $isOver = $active && now()->gt($active->voting_end);

        if (!$active || $isOver) {
            if (isset($settings['allowVoting']) && $settings['allowVoting'] == true) {
                Setting::updateOrCreate(['key' => 'allowVoting'], ['value' => 0]);
            }
            $this->allowVoting = false;
        } else {
            $this->allowVoting = (bool) ($settings['allowVoting'] ?? false);
        }

        $this->showResults = (bool) ($settings['showResults'] ?? false);
        $this->lockChanges = (bool) ($settings['lockChanges'] ?? false);
        $this->maintenanceMode = (bool) ($settings['maintenanceMode'] ?? false);
    }

    public function updateProgress()
    {
        $active = $this->active;
        if (!$active) {
            return;
        }

        $now = now();

        if ($active->filing_start && $active->filing_end) {
            if ($now->between($active->filing_start, $active->filing_end)) {
                $total = $active->filing_start->diffInSeconds($active->filing_end);
                $elapsed = $active->filing_start->diffInSeconds($now);
                $this->filingProgress = (int) (($elapsed / max($total, 1)) * 100);
            } elseif ($now->gt($active->filing_end)) {
                $this->filingProgress = 100;
            }
        }

        if ($active->campaign_start && $active->campaign_end) {
            if ($now->between($active->campaign_start, $active->campaign_end)) {
                $total = $active->campaign_start->diffInSeconds($active->campaign_end);
                $elapsed = $active->campaign_start->diffInSeconds($now);
                $this->campaignProgress = (int) (($elapsed / max($total, 1)) * 100);
            } elseif ($now->gt($active->campaign_end)) {
                $this->campaignProgress = 100;
            }
        }

        if ($active->voting_start && $active->voting_end) {
            if ($now->between($active->voting_start, $active->voting_end)) {
                $total = $active->voting_start->diffInSeconds($active->voting_end);
                $elapsed = $active->voting_start->diffInSeconds($now);
                $this->progress = (int) (($elapsed / max($total, 1)) * 100);
            } elseif ($now->gt($active->voting_end)) {
                $this->progress = 100;
            }
        }
    }

    public function checkAutoOff()
    {
        $this->updateProgress();
        $active = $this->active;
        if ($active) {
            $now = now();

            if ($now->between($active->voting_start, $active->voting_end)) {
                if (!$this->allowVoting) {
                    $this->allowVoting = true;
                    Setting::updateOrCreate(['key' => 'allowVoting'], ['value' => 1]);

                    if (!$active->notifications_sent) {
                        \App\Jobs\SendElectionStartedEmail::dispatch();

                        $active->update(['notifications_sent' => true]);
                    }

                    $this->dispatch('swal', [
                        'title' => 'Election is Live',
                        'text' => 'The voting portal is now open and notification emails are being sent.',
                        'icon' => 'success',
                    ]);
                }
            } elseif ($now->greaterThan($active->voting_end) && $now->lessThan($active->results_date)) {
                if ($this->allowVoting) {
                    $this->allowVoting = false;
                    Setting::updateOrCreate(['key' => 'allowVoting'], ['value' => 0]);

                    $this->dispatch('swal', [
                        'title' => 'Election Closed',
                        'text' => 'The voting period has ended automatically.',
                        'icon' => 'info',
                    ]);
                }
            }

            if ($now->greaterThan($active->results_date)) {
                if ($active->status === 'active') {
                    $active->update(['status' => 'completed']);

                    Setting::updateOrCreate(['key' => 'allowVoting'], ['value' => 0]);
                    $this->allowVoting = false;

                    $this->dispatch('swal', [
                        'title' => 'Cycle Completed',
                        'text' => 'The election cycle has been automatically finalized.',
                        'icon' => 'success',
                    ]);

                    unset($this->active);
                    $this->mount();
                }
            }
        }
    }

    public function toggleSetting(string $setting)
    {
        if (property_exists($this, $setting)) {
            $active = $this->active;
            $now = now();

            if ($setting === 'allowVoting' && !$this->allowVoting) {
                if (!$active || $now->lt($active->voting_start)) {
                    $this->dispatch('swal', [
                        'title' => 'Access Restricted',
                        'text' => 'Voting cannot be enabled yet. Please wait for the scheduled start date.',
                        'icon' => 'error',
                    ]);
                    return;
                }

                if ($now->gt($active->voting_end)) {
                    $this->dispatch('swal', [
                        'title' => 'Election Closed',
                        'text' => 'The voting period has already ended.',
                        'icon' => 'warning',
                    ]);
                    $this->allowVoting = false;
                    Setting::updateOrCreate(['key' => 'allowVoting'], ['value' => 0]);
                    return;
                }
            }

            $this->{$setting} = !$this->{$setting};
            Setting::updateOrCreate(['key' => $setting], ['value' => $this->{$setting} ? 1 : 0]);

            $this->dispatch('swal', [
                'title' => 'Setting Updated',
                'text' => 'The configuration has been successfully changed.',
                'icon' => 'success',
            ]);

            if ($setting === 'maintenanceMode' && $this->maintenanceMode) {
                Cache::forget('maintenanceMode');
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();
                $this->redirect('/');
                return;
            }

            if ($setting === 'maintenanceMode' && !$this->maintenanceMode) {
                Cache::forget('maintenanceMode');
                return;
            }
        }
    }

    public function createNewCycle()
    {
        $studentCount = Student::where('status', 'active')->count();

        if ($studentCount === 0) {
            $missing = [];
            if ($studentCount === 0) {
                $missing[] = 'Active Students';
            }

            $this->dispatch('swal', [
                'title' => 'Missing Requirements',
                'text' => 'Cannot create a new cycle. The following data is missing: ' . implode(', ', $missing),
                'icon' => 'warning',
            ]);
            return;
        }

        $request = new ElectionCycleRequest();
        $this->validate($request->rules());

        try {
            DB::transaction(function () {
                ElectionCycle::where('status', 'active')->update(['status' => 'completed']);

                Student::query()->update([
                    'vote_reference' => null,
                    'has_voted' => 0,
                    'voted_at' => null,
                ]);

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

            cache()->forget('admin_dashboard_data');
            Cache::forget('active_election_cycle');

            unset($this->active);
            $this->reset(['cycle_name', 'academic_year', 'filing_start', 'filing_end', 'campaign_start', 'campaign_end', 'start_date', 'end_date', 'results_date']);
            $this->mount();

            $this->dispatch('close-modal', id: 'newCycleModal');

            $this->dispatch('update-admin-charts');

            $this->dispatch('swal', [
                'title' => 'Cycle Initialized!',
                'text' => 'A new election cycle has been successfully started.',
                'icon' => 'success',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'title' => 'Initialization Failed',
                'text' => 'An error occurred while starting the cycle: ' . $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    public function updateDates()
    {
        $request = new ElectionCycleRequest();
        $rules = collect($request->rules())
            ->only(['filing_start', 'filing_end', 'campaign_start', 'campaign_end', 'start_date', 'end_date'])
            ->toArray();

        $this->validate($rules);
        try {
            $active = $this->active;
            if ($active) {
                $active->update([
                    'filing_start' => $this->filing_start,
                    'filing_end' => $this->filing_end,
                    'campaign_start' => $this->campaign_start,
                    'campaign_end' => $this->campaign_end,
                    'voting_start' => $this->start_date,
                    'voting_end' => $this->end_date,
                ]);

                Cache::forget('active_election_cycle');

                unset($this->active);
                $this->mount();
                $this->dispatch('close-modal', id: 'updateDatesModal');

                $this->dispatch('swal', [
                    'title' => 'Dates Updated!',
                    'text' => 'Election cycle schedule has been successfully updated.',
                    'icon' => 'success',
                    'redirect' => '/admin/election-cycle',
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'title' => 'Update Error',
                'text' => 'An error occurred while updating dates: ' . $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    public function endPhase($phase)
    {
        try {
            $active = $this->active;
            // if ($active) {
            //     $now = now();
            //     if ($now->lt($active->filing_end)) {
            //         $this->dispatch('swal', [
            //             'title' => 'Action Prohibited',
            //             'text' => 'You cannot end the election while the Filing Phase is still ongoing.',
            //             'icon' => 'error',
            //         ]);
            //         return;
            //     }

            //     $active->update(['status' => 'completed']);
            // }

            $now = now();

            // if ($phase === 'filing') {
            //     $candidates = \App\Models\Candidate::where('election_cycle_id', $active->id)->get();

            //     if ($candidates->isEmpty()) {
            //         $this->dispatch('swal', [
            //             'title' => 'Requirement Failed',
            //             'text' => 'Cannot end filing: No candidates have registered for this cycle.',
            //             'icon' => 'error',
            //         ]);
            //         return;
            //     }

            //     foreach ($candidates as $candidate) {
            //         if (!$candidate->isProfileComplete()) {
            //             $this->dispatch('swal', [
            //                 'title' => 'Incomplete Profiles',
            //                 'text' => "Candidate {$candidate->student->first_name} {$candidate->student->last_name} has an incomplete profile or platform.",
            //                 'icon' => 'warning',
            //             ]);
            //             return;
            //         }
            //     }
            // }

            switch ($phase) {
                case 'filing':
                    $active->update([
                        'filing_start' => $now->copy()->subMinute(),
                        'filing_end' => $now,
                    ]);
                    break;

                case 'campaign':
                    $active->update([
                        'campaign_start' => $now->copy()->subMinute(),
                        'campaign_end' => $now,
                        'voting_start' => $now,
                        'voting_end' => $active->voting_end,
                    ]);
                    break;
            }

            Cache::forget('active_election_cycle');

            unset($this->active);
            $this->mount();

            $this->dispatch('swal', [
                'title' => 'Phase Updated',
                'text' => 'The ' . ucfirst($phase) . ' phase has been successfully closed.',
                'icon' => 'success',
                'redirect' => '/admin/election-cycle',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'title' => 'Error',
                'text' => 'Something went wrong: ' . $e->getMessage(),
                'icon' => 'error',
            ]);
        }
    }

    public function endElection()
    {
        try {
            $active = $this->active;
            if ($active) {
                $active->update(['status' => 'completed']);
            }

            Cache::forget('active_election_cycle');

            unset($this->active);
            $this->mount();

            $this->dispatch('swal', [
                'title' => 'Election Completed!',
                'text' => 'The election cycle has been successfully closed.',
                'icon' => 'success',
                'redirect' => '/admin/election-cycle',
            ]);
        } catch (\Exception $e) {
            $this->dispatch('swal', [
                'title' => 'Closure Error',
                'text' => 'Could not end the election cycle: ' . $e->getMessage(),
                'icon' => 'error',
            ]);
        }
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
        <div class="topbar" wire:key="cycle-topbar">
            <div class="topbar-info">
                <h2 class="fw-bold text-primary">Election <span class="text-accent">Cycle</span></h2>
                <p class="text-muted mb-0 small">Configure election dates and live system controls</p>
            </div>
            @if ($this->active)
                <div class="btn-glow d-flex align-items-center justify-content-center w-[80px] md:w-[200px] mb-2 md:mb-0 p-2 md:p-3 bg-success text-white rounded-pill"
                    style="height: 38px; border-radius: 8px; cursor: default;" title="Election Process Ongoing">
                    <i class="bi bi-clock-history fs-7 p-1"></i>
                    <span class="hidden md:inline ms-1" style="font-size: 14px;">Ongoing Election</span>
                </div>
            @else
                <x-button variant="glow" class="w-full xs:w-auto px-4 md:px-6" data-bs-toggle="modal"
                    data-bs-target="#newCycleModal" title="New Election Cycle">
                    <i class="bi bi-plus-lg fs-7 p-1"></i>
                    <span class="hidden md:inline ms-1">New Election Cycle</span>
                </x-button>
            @endif
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
                            :class="!dates.rD ? 'bg-secondary border' : (now < dates.rD ?
                                'bg-success-subtle text-success border border-success' :
                                'bg-danger-subtle text-danger border border-danger')">

                            <span x-show="dates.rD" class="pulse-dot" style="width: 6px; height: 6px;"
                                :style="'background: ' + (now < dates.rD ? '#10b981' : '#ef4444')"></span>
                            <span class="fw-bold"
                                x-text="!dates.rD ? 'No Cycle Active' : (now < dates.rD ? 'Election Cycle Active' : 'Cycle Completed')">
                            </span>
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
                                        'color' => 'text-warning',
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
                                        'color' => 'text-warning',
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
                                <div class="p-2 p-md-3 border rounded-3 timeline-box shadow-sm position-relative"
                                    :class="now >= dates.{{ $phase['start'] }} && (!dates.{{ $phase['end'] ?? 'null' }} ||
                                        now < dates.{{ $phase['end'] ?? 'null' }}) ? 'active border-primary' : ''">

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1 overflow-hidden">
                                            <h6 class="fw-bold mb-0 uppercase text-[10px] md:text-xs text-muted">
                                                {{ $phase['title'] }}
                                            </h6>

                                            @if ($phase['end'])
                                                <template x-if="now < dates.{{ $phase['start'] }}">
                                                    <div class="fw-bold text-primary mt-1 text-[11px] md:text-sm">Starts
                                                        in:
                                                        <span
                                                            x-text="pad(getRemaining(dates.{{ $phase['start'] }}).d) + 'd ' + pad(getRemaining(dates.{{ $phase['start'] }}).h) + 'h'"></span>
                                                    </div>
                                                </template>
                                                <template
                                                    x-if="now >= dates.{{ $phase['start'] }} && now < dates.{{ $phase['end'] }}">
                                                    <div class="d-flex align-items-center gap-2 mt-1">
                                                        <div
                                                            class="fw-bold {{ $phase['color'] }} text-[11px] md:text-sm">
                                                            Ends in: <span
                                                                x-text="pad(getRemaining(dates.{{ $phase['end'] }}).d) + 'd ' + pad(getRemaining(dates.{{ $phase['end'] }}).h) + 'h'"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="now >= dates.{{ $phase['end'] }}">
                                                    <div
                                                        class="fw-bold text-muted mt-1 text-[11px] md:text-sm text-truncate">
                                                        <i class="bi bi-check-circle-fill me-1 text-success"></i>Closed
                                                    </div>
                                                </template>
                                                @if ($phase['id'] === 'f' || $phase['id'] === 'c')
                                                    <template x-if="now < dates.{{ $phase['end'] }}">
                                                        <div class="mt-2">
                                                            <button type="button"
                                                                @click.prevent="
                                                                    Swal.fire({
                                                                        title: 'End {{ $phase['id'] === 'f' ? 'Filing' : 'Campaign' }} Phase?',
                                                                        text: 'Do you want to end this phase now?',
                                                                        icon: 'warning',
                                                                        showCancelButton: true,
                                                                        confirmButtonColor: '#d33',
                                                                        confirmButtonText: 'Yes, End it!'
                                                                    }).then((result) => {
                                                                        if (result.isConfirmed) {
                                                                            $wire.endPhase('{{ $phase['id'] === 'f' ? 'filing' : 'campaign' }}')
                                                                        }
                                                                    })
                                                                "
                                                                class="btn btn-danger btn-xs py-1 px-1 fw-bold shadow-sm"
                                                                style="font-size: 10px; border-radius: 5px;">
                                                                END {{ $phase['id'] === 'f' ? 'FILING' : 'CAMPAIGN' }}
                                                            </button>
                                                        </div>
                                                    </template>
                                                @endif
                                            @else
                                                <template x-if="now < dates.rD">
                                                    <div class="fw-bold text-primary mt-1 text-[11px] md:text-sm">
                                                        Scheduled:
                                                        <span
                                                            x-text="pad(getRemaining(dates.rD).d) + 'd ' + pad(getRemaining(dates.rD).h) + 'h ' + pad(getRemaining(dates.rD).m) + 'm'"></span>
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
                                            :class="now < dates.{{ $phase['start'] }} ?
                                                (dates.vE && now >= dates.vE && '{{ $phase['id'] }}' == 'r' ?
                                                    'bg-warning' : 'bg-secondary') :
                                                ({{ $phase['end'] ? 'now < dates.' . $phase['end'] : ($status === 'active' ? 'true' : 'false') }} ?
                                                    'bg-warning' : 'bg-success')">

                                            <span
                                                x-text="now < dates.{{ $phase['start'] }} ?
                                                (dates.vE && now >= dates.vE && '{{ $phase['id'] }}' == 'r' ? 'Ongoing' : 'Upcoming') :
                                                ({{ $phase['end'] ? 'now < dates.' . $phase['end'] : ($status === 'active' ? 'true' : 'false') }} ? 'Ongoing' : 'Completed')">
                                            </span>
                                        </span>
                                    </div>
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
                <div class="glass-card p-3 p-md-4 border-0 shadow-sm bg-white h-100">
                    <h6 class="fw-bold text-primary mb-3 mb-md-4 uppercase small">
                        <i class="bi bi-sliders me-2"></i>Live Controls
                    </h6>
                    <div wire:poll.5s="checkAutoOff">
                        @foreach ([['key' => 'allowVoting', 'label' => 'Allow Voting', 'desc' => 'Open the voting portal for students'], ['key' => 'showResults', 'label' => 'Show Election Results', 'desc' => 'Display the tally to the student dashboard'], ['key' => 'maintenanceMode', 'label' => 'Maintenance', 'desc' => 'Instantly freeze site activity']] as $setting)
                            <div
                                class="control-item d-flex justify-content-between align-items-center mb-2 mb-md-3 p-2 rounded-3 hover-bg-light border shadow-xs">
                                <div class="pe-2">
                                    <div class="fw-bold text-dark text-[13px] md:text-sm">{{ $setting['label'] }}</div>
                                    <div class="text-muted text-[10px] md:text-xs leading-tight">
                                        @if ($setting['key'] == 'allowVoting')
                                            <span
                                                :class="!dates.vE ? 'text-muted' : (now > dates.vE ? 'text-danger fw-bold' : (
                                                    now >= dates.vS && now <= dates.vE ?
                                                    'text-success fw-bold' : 'text-warning'))">
                                                <i class="bi bi-clock me-1"></i>
                                                <span
                                                    x-text="!dates.vE ? 'No Active Cycle' : (now > dates.vE ? 'Voting Period Ended' : (now >= dates.vS ? 'Election Process Ongoing' : 'Auto-scheduled'))"></span>
                                            </span>
                                        @else
                                            {{ $setting['desc'] }}
                                        @endif
                                    </div>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                        style="cursor: pointer;" :checked="$wire.get('{{ $setting['key'] }}')"
                                        @click.prevent="
                                        const key = '{{ $setting['key'] }}';
                                        const label = '{{ $setting['label'] }}';
                                        const isCurrentlyOn = $wire.get(key);

                                        if (key === 'allowVoting') {
                                            if (!dates.vE) {
                                                return Swal.fire({
                                                    title: 'No Active Cycle',
                                                    text: 'Please create an election cycle first.',
                                                    icon: 'warning'
                                                });
                                            }
                                            if (now > dates.vE) {
                                                return Swal.fire({
                                                    title: 'Notice',
                                                    text: 'The voting period has already ended.',
                                                    icon: 'info'
                                                });
                                            }
                                            if (!isCurrentlyOn && now < dates.vS) {
                                                return Swal.fire({
                                                    title: 'Access Restricted',
                                                    text: 'Voting cannot be enabled yet.',
                                                    icon: 'error'
                                                });
                                            }
                                        }

                                        Swal.fire({
                                            title: 'Confirm Action',
                                            text: `Are you sure you want to ${isCurrentlyOn ? 'disable' : 'enable'} ${label}?`,
                                            icon: 'warning',
                                            showCancelButton: true,
                                            confirmButtonColor: '#3085d6',
                                            confirmButtonText: 'Yes, proceed!'
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                $wire.toggleSetting(key);
                                            }
                                        });
                                    ">
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-3 mt-md-4 pt-3 border-top d-flex flex-column gap-2 mb-12 mb-md-0">
                            <button class="btn btn-outline-primary btn-sm w-100 py-2 fw-bold text-[12px] md:text-sm"
                                @click.prevent="
                                Swal.fire({
                                    title: 'Update Schedule?',
                                    text: 'Changing the dates may affect the current election flow.',
                                    icon: 'info',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#6c757d',
                                    confirmButtonText: 'Yes, Open Editor'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        new bootstrap.Modal(document.getElementById('updateDatesModal')).show();
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
                                        cancelButtonColor: '#6c757d',
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
                                <input type="text" wire:model="cycle_name"
                                    class="form-control-modern py-1 py-md-2" placeholder="e.g. CSC General Election"
                                    style="font-size: 0.85rem;">
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

    <div wire:ignore.self class="modal fade" id="updateDatesModal" tabindex="-1"
        aria-labelledby="updateDatesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg px-2">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
                <div class="modal-header bg-primary text-white p-3 p-md-4">
                    <h5 class="modal-title fw-bold" id="updateDatesModalLabel">
                        <i class="bi bi-calendar-event-fill me-2"></i>Adjust Election Timeline
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <form wire:submit.prevent="updateDates">
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            <div class="col-12">
                                <label
                                    class="form-label fw-bold small text-muted text-uppercase d-flex align-items-center">
                                    <i class="bi bi-1-circle-fill me-2 text-primary"></i> Phase 1: Filing Period
                                </label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-white border-end-0 text-muted">
                                        <i class="bi bi-pencil-square"></i>
                                    </span>
                                    <input type="date" wire:model="filing_start"
                                        class="form-control border-start-0 @error('filing_start') is-invalid @enderror"
                                        title="Start Date">
                                    <span
                                        class="input-group-text bg-white border-start-0 border-end-0 text-muted">to</span>
                                    <input type="date" wire:model="filing_end"
                                        class="form-control border-start-0 @error('filing_end') is-invalid @enderror"
                                        title="End Date">
                                </div>
                                @error('filing_start')
                                    <span class="text-danger small mt-1 d-block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label
                                    class="form-label fw-bold small text-muted text-uppercase d-flex align-items-center">
                                    <i class="bi bi-2-circle-fill me-2 text-primary"></i> Phase 2: Campaign Period
                                </label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-white border-end-0 text-muted">
                                        <i class="bi bi-megaphone"></i>
                                    </span>
                                    <input type="date" wire:model="campaign_start"
                                        class="form-control border-start-0 @error('campaign_start') is-invalid @enderror"
                                        title="Start Date">
                                    <span
                                        class="input-group-text bg-white border-start-0 border-end-0 text-muted">to</span>
                                    <input type="date" wire:model="campaign_end"
                                        class="form-control border-start-0 @error('campaign_end') is-invalid @enderror"
                                        title="End Date">
                                </div>
                            </div>
                            <div class="col-12 border-top pt-4 mt-2">
                                <label
                                    class="form-label fw-bold small text-primary text-uppercase d-flex align-items-center">
                                    <i class="bi bi-3-circle-fill me-2"></i> Phase 3: Voting Period (Live)
                                </label>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="small text-muted fw-semibold mb-1">Starts:</label>
                                        <div class="input-group shadow-sm">
                                            <span class="input-group-text bg-white text-success"><i
                                                    class="bi bi-play-fill"></i></span>
                                            <input type="datetime-local" wire:model="start_date"
                                                class="form-control @error('start_date') is-invalid @enderror">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small text-muted fw-semibold mb-1">Ends:</label>
                                        <div class="input-group shadow-sm">
                                            <span class="input-group-text bg-white text-danger"><i
                                                    class="bi bi-stop-fill"></i></span>
                                            <input type="datetime-local" wire:model="end_date"
                                                class="form-control @error('end_date') is-invalid @enderror">
                                        </div>
                                    </div>
                                </div>
                                @error('end_date')
                                    <span class="text-danger small mt-2 d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 p-3"
                        style="border-bottom-left-radius: 15px; border-bottom-right-radius: 15px;">
                        <button type="button" class="btn btn-secondary px-4 border"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit"
                            class="btn btn-primary px-4 fw-bold shadow-sm d-flex align-items-center">
                            <span wire:loading.remove wire:target="updateDates">
                                <i class="bi bi-check-circle me-2"></i>Save Timeline
                            </span>
                            <span wire:loading wire:target="updateDates">
                                <span class="spinner-border spinner-border-sm me-2"></span>Saving...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php

use App\Models\Setting;
use Livewire\Volt\Component;
use App\Models\ElectionCycle;
use Illuminate\Support\Facades\{Auth, Session};
use Livewire\Attributes\{Layout, On, Title, Computed};

new #[Layout('layouts.app')] #[Title('Student Dashboard')] class extends Component {
    // 1. STATE PROPERTIES
    public $student;
    public $profile_photo_path;
    public $studentCourse;
    public bool $isVotingOpen = false;
    public bool $isResultsVisible = false;

    // 2. LIFECYCLE HOOKS
    public function mount()
    {
        $user = Auth::user()?->load('student');
        $this->student = $user?->student;
        $this->studentCourse = $this->student->course ?? 'General';

        if ($this->student) {
            $this->profile_photo_path = $this->student->photo ?? ($this->student->profile_photo_path ?? null);
        }

        $settings = Setting::pluck('value', 'key')->toArray();
        $this->isVotingOpen = (bool) ($settings['allowVoting'] ?? false);
        $this->isResultsVisible = (bool) ($settings['showResults'] ?? false);
    }

    // 3. COMPUTED PROPERTIES
    #[Computed]
    public function activeCycle()
    {
        return ElectionCycle::where('status', 'active')->latest()->first();
    }

    #[Computed]
    public function isVotingOpen()
    {
        $setting = Setting::where('key', 'allowVoting')->first();
        $activeCycle = $this->activeCycle;

        if (!$setting || !(bool) $setting->value) {
            return false;
        }

        if (!$activeCycle || now()->gt($activeCycle->voting_end)) {
            return false;
        }

        return true;
    }

    #[Computed]
    public function tallyData()
    {
        if (!$this->isResultsVisible) {
            return [];
        }

        $voterCourse = $this->student->course ?? null;
        return cache()->remember('tally_results_' . ($voterCourse ?? 'all'), 60, function () use ($voterCourse) {
            return \App\Models\Candidate::with(['student', 'position'])
                ->withCount('votes')
                ->whereHas('position', fn($query) => $query->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourse))
                ->join('positions', 'candidates.position_id', '=', 'positions.id')
                ->orderBy('positions.priority', 'asc')
                ->orderBy('votes_count', 'desc')
                ->select('candidates.*')
                ->get()
                ->map(
                    fn($candidate) => [
                        'label' => ($candidate->student->last_name ?? 'Unknown') . ' (' . ($candidate->position->name ?? 'N/A') . ')',
                        'votes' => $candidate->votes_count ?? 0,
                    ],
                )
                ->values()
                ->toArray();
        });
    }

    // 4. EVENT LISTENERS
    #[On('echo-private:election-results.{studentCourse},VoteUpdated')]
    public function refreshTally()
    {
        cache()->forget('tally_results_' . ($this->studentCourse ?? 'all'));
        $this->dispatch('update-chart', ['tally' => $this->tallyData()]);
    }

    // 5. ACTION / HELPER METHODS
    public function checkMaintenance()
    {
        $isMaintenance = Setting::where('key', 'maintenanceMode')->value('value');

        if ($isMaintenance == '1' || $isMaintenance === true) {
            $this->dispatch('swal-maintenance', [
                'icon' => 'warning',
                'title' => 'System Maintenance',
                'text' => 'The system is undergoing maintenance. You will be logged out.',
            ]);
        }
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
        return redirect()->route('login');
    }
}; ?>

<div wire:poll.10s="checkMaintenance">
    @include('layouts.partials.student-sidebar')
    <main class="main-content">
        <div class="topbar" wire:key="persistent-topbar-header">
            <div>
                <h2 class="mb-0">Student <span class="text-primary">Dashboard</span></h2>
                <p class="text-secondary mb-0">
                    Welcome back, <span class="text-primary">{{ $student->first_name ?? 'Student' }}
                        {{ $student->middle_name ? substr($student->middle_name, 0, 1) . '.' : 'N/A' }}
                        {{ $student->last_name ?? 'Student' }}
                        {{ $student->suffix ?? '' }}</span>
                </p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-8">
                <div class="glass-card p-3 h-100 border-0 shadow-sm">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="icon-box bg-info-light p-2 rounded">
                            <i class="bi bi-info-circle text-info text-primary"></i>
                        </div>
                        <h6 class="mb-0 fw-bold text-dark">Quick Guide & Election Rules</h6>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-primary fw-bold d-block mb-2">
                                <i class="bi bi-list-check me-1"></i> How to Vote:
                            </small>
                            <ul class="list-unstyled mb-0" style="font-size: 0.75rem; color: #64748b;">
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="bi bi-1-circle text-primary me-2"></i>
                                    <span>Click the <strong>"Vote Now"</strong> button.</span>
                                </li>
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="bi bi-2-circle text-primary me-2"></i>
                                    <span>Select one candidate per position.</span>
                                </li>
                                <li class="mb-0 d-flex align-items-start">
                                    <i class="bi bi-3-circle text-primary me-2"></i>
                                    <span>Review and <strong>Submit</strong> your ballot.</span>
                                </li>
                            </ul>
                        </div>

                        <div class="col-md-6 border-start-md">
                            <small class="text-danger fw-bold d-block mb-2">
                                <i class="bi bi-exclamation-triangle me-1"></i> Important Rules:
                            </small>
                            <ul class="list-unstyled mb-0" style="font-size: 0.75rem; color: #64748b;">
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="bi bi-dot fs-5 leading-none text-danger"></i>
                                    <span>You can only vote <strong>once</strong>.</span>
                                </li>
                                <li class="mb-2 d-flex align-items-start">
                                    <i class="bi bi-dot fs-5 leading-none text-danger"></i>
                                    <span>Votes cannot be edited after submission.</span>
                                </li>
                                <li class="mb-0 d-flex align-items-start">
                                    <i class="bi bi-dot fs-5 leading-none text-danger"></i>
                                    <span>Your vote is 100% anonymous.</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="glass-card p-3 h-100 border-0 shadow-sm d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="icon-box bg-primary-light p-2 rounded">
                                <i class="bi bi-box-seam text-primary"></i>
                            </div>
                            <h6 class="mb-0 fw-bold text-primary" style="font-size: clamp(0.85rem, 2vw, 1rem);">
                                Cast Your Vote
                            </h6>
                        </div>

                        @if ($this->isVotingOpen)
                            <p class="text-success small pb-2">
                                <i class="bi bi-check-circle-fill me-1"></i> The election is live.
                            </p>
                            <a href="/students/cast-vote" wire:navigate
                                class="btn btn-glow btn-sm w-100 py-2 d-inline-flex align-items-center justify-content-center">
                                Vote Now
                            </a>
                        @else
                            @php
                                $latestCycle = \App\Models\ElectionCycle::latest()->first();
                            @endphp

                            @if ($latestCycle && ($latestCycle->status === 'finished' || $latestCycle->status === 'completed'))
                                <p class="text-danger small" style="font-size: 0.80rem">
                                    <i class="bi bi-lock-fill me-1"></i> Voting is no longer active.
                                </p>
                                <button class="btn btn-secondary btn-sm w-100 fw-bold" disabled>
                                    <i class="bi bi-slash-circle me-2"></i> Voting Closed
                                </button>

                                @if ($latestCycle->voting_end)
                                    <div class="mt-2 text-muted text-center text-md-start" style="font-size: 0.80rem">
                                        The voting period concluded on
                                        <strong>{{ $latestCycle->voting_end->format('M d, Y h:i A') }}</strong>.
                                    </div>
                                @endif
                            @else
                                <p class="text-warning text-center text-md-start mb-1 mb-md-0 small"
                                    style="font-size: 0.80rem">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> No ongoing election.
                                </p>
                                <button class="btn btn-light btn-sm w-100 fw-bold text-muted border" disabled>
                                    <i class="bi bi-hourglass-top me-2"></i> Not Yet Available
                                </button>
                                <div class="mt-2 text-muted text-center text-md-start" style="font-size: 0.80rem">
                                    Please stand by until an administrator initiates the next voting cycle.
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 grid grid-cols-1">
            <div class="col-12 w-full">
                <div class="glass-card p-2 md:p-6 mb-3 border-0 shadow-sm">
                    <div class="d-flex flex-col sm:flex-row justify-content-between align-items-center mb-3 gap-3">
                        <h5 class="text-dark mb-0 fw-bold flex items-center">
                            <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Live Election Standings
                        </h5>

                        <span class="badge px-3 py-2 rounded-pill shadow-sm w-fit"
                            style="background-color: #10b981; color: white; border: none; font-size: 0.85rem;">
                            <i class="bi bi-mortarboard-fill me-1"></i>
                            {{ match ($student->course ?? 'General') {
                                'IT' => 'Information Technology',
                                'HRTM' => 'Hotel and Restaurant Management',
                                'HST' => 'Hospitality Service Technology',
                                'ECT' => 'Electronic Computer Technology',
                                default => $student->course ?? 'General',
                            } }}
                        </span>
                    </div>

                    @if ($isResultsVisible)
                        <div class="relative h-[300px] md:h-[400px]" style="position: relative;" wire:ignore>
                            <div id="mainTallyChart"></div>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="p-3 bg-light d-inline-block rounded-circle mb-3">
                                <i class="bi bi-eye-slash fs-2 text-muted bg-light rounded-circle d-inline-flex align-items-center justify-content-center"
                                    style="width: 30px; height: 30px;"></i>
                            </div>
                            <h6 class="fw-bold text-dark">Vote Tallying is Hidden</h6>
                            <p class="text-muted small px-4">Live results are currently restricted by the administrator.
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>

    @script
        <script>
            let myChart = null;

            const renderChart = (newData = null) => {
                const container = document.getElementById('mainTallyChart');
                if (!container) return;

                const tally = newData ? newData : @json($this->tallyData);
                const labels = tally.map(item => item.label);
                const votes = tally.map(item => item.votes);

                const options = {
                    chart: {
                        type: 'bar',
                        height: 400,
                        toolbar: {
                            show: false
                        },
                        animations: {
                            enabled: true
                        }
                    },
                    series: [{
                        name: 'Votes',
                        data: votes
                    }],
                    colors: ['#10b981'],
                    plotOptions: {
                        bar: {
                            borderRadius: 4,
                            horizontal: true,
                            barHeight: '70%',
                            distributed: false
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: function(val) {
                            return Math.floor(val);
                        }
                    },
                    xaxis: {
                        categories: labels,
                        labels: {
                            formatter: function(val) {
                                return Math.floor(val);
                            }
                        }
                    },
                    yaxis: {
                        labels: {
                            style: {
                                fontSize: '12px',
                                fontWeight: 600
                            }
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: (val) => Math.floor(val) + " Votes"
                        }
                    }
                };

                if (myChart) {
                    myChart.updateOptions({
                        xaxis: {
                            categories: labels
                        }
                    });
                    myChart.updateSeries([{
                        data: votes
                    }]);
                } else {
                    myChart = new ApexCharts(container, options);
                    myChart.render();
                }
            };

            renderChart();

            $wire.on('update-chart', (payload) => {
                const eventData = Array.isArray(payload) ? payload[0] : payload;
                renderChart(eventData.tally);
            });

            document.addEventListener('livewire:navigated', () => {
                if (myChart && typeof myChart.destroy === 'function') myChart.destroy();
                myChart = null;
                renderChart();
            });
        </script>
    @endscript
</div>

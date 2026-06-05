<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\{Layout, On, Title, Computed};
use App\Traits\{ChecksMaintenance, AuthenticatesLogout};
use App\Services\Student\DashboardService;

new #[Layout('layouts.app')] #[Title('Student Dashboard')] class extends Component {
    use ChecksMaintenance, AuthenticatesLogout;

    public $student;
    public $profile_photo_path;
    public $studentCourse;
    public bool $isResultsVisible = false;

    private DashboardService $dashboardService;

    public function boot(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

    public function mount()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $data = $this->dashboardService->getStudentData($user);
        $this->student = $data['student'];
        $this->studentCourse = $data['studentCourse'];
        $this->profile_photo_path = $data['profile_photo_path'];
        $this->isResultsVisible = $this->dashboardService->isResultsVisible();
    }

    #[Computed]
    public function activeCycle()
    {
        return $this->dashboardService->getActiveCycle();
    }

    #[Computed]
    public function isVotingOpen()
    {
        return $this->dashboardService->isVotingOpen($this->activeCycle);
    }

    #[Computed]
    public function tallyData()
    {
        $courseId = $this->dashboardService->getStudentCourseId($this->student);
        return $this->dashboardService->getTallyData($this->activeCycle, $courseId, $this->isResultsVisible);
    }

    #[On('echo-private:election-results.{studentCourse},VoteUpdated')]
    public function refreshTally()
    {
        $activeCycle = $this->dashboardService->getActiveCycle();
        if ($activeCycle) {
            $courseId = $this->dashboardService->getStudentCourseId($this->student);
            $this->dashboardService->forgetTallyCache($activeCycle, $courseId);
        }
        $this->dispatch('update-chart', ['tally' => $this->tallyData()]);
    }
}; ?>

<div>
    @include('layouts.partials.student-sidebar')

    @php
        $suffix = auth()->user()->student->suffix;
        $formattedSuffix = in_array($suffix, ['Jr', 'Sr']) ? $suffix . '.' : $suffix;
    @endphp

    <main class="main-content">
        <div class="topbar" wire:key="persistent-topbar-header">
            <div>
                <h2 class="mb-0">Student <span class="text-primary">Dashboard</span></h2>
                <p class="text-secondary mb-0 small">
                    Welcome back, <span class="text-primary">{{ $student->first_name ?? 'Student' }}
                        {{ $student->middle_name ? substr($student->middle_name, 0, 1) . '.' : '' }}
                        {{ $student->last_name ?? 'Student' }}
                        {{ $formattedSuffix ?? '' }}</span>
                </p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-8">
                <div class="glass-card p-2 h-100 border-0 shadow-sm">
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
                            <a href="/students/cast-vote" wire:navigate wire:loading.attr="disabled"
                                class="btn btn-glow w-100 py-1">
                                Vote Now
                            </a>
                        @else
                            @php
                                $latestCycle = ElectionCycle::latest()->first();
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
                    <div class="flex flex-row justify-between items-center mb-3 px-1 md-px-3">
                        <h5 class="text-dark mb-0 font-bold flex items-center">
                            <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Live Election Standings
                        </h5>
                        <span class="badge px-2 py-1 rounded-full shadow-sm text-white"
                            style="background-color: #10b981; font-size: 0.75rem;">
                            <i class="bi bi-mortarboard-fill me-1"></i>
                            {{ app(\App\Services\Student\DashboardService::class)->getCourseDisplayName($student->block->course->name ?? null) }}
                        </span>
                    </div>

                    @if ($isResultsVisible)
                        <div class="relative h-[500px] md:h-[400px] mb-4 md:mb-0" style="position: relative;"
                            wire:ignore>
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

                const tally = newData ? newData : @json($this->tallyData());
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

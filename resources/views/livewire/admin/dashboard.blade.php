<?php

use Livewire\Volt\Component;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Traits\AuthenticatesLogout;
use Livewire\Attributes\{Layout, Title, On};
use Illuminate\Support\Facades\{Auth, Session};
use App\Models\{Vote, Candidate, Student, ElectionCycle, Course};

new #[Layout('layouts.admin'), Title('Admin Dashboard')] class extends Component {
    use AuthenticatesLogout;
    public function getActiveProperty()
    {
        return ElectionCycle::getActiveCycle();
    }

    private function getDashboardData($forceRefresh = false): array
    {
        $activeCycle = $this->active;
        $cacheKey = 'admin_dashboard_data_' . ($activeCycle ? $activeCycle->id : 'none');

        if ($forceRefresh) {
            cache()->forget('admin_dashboard_data');
        }

        return cache()->remember($cacheKey, now()->addMinutes(10), function () use ($activeCycle) {
            $activeCycle = $this->active;
            $activeCycleId = $activeCycle ? $activeCycle->id : 0;

            $now = now();
            $targetDate = null;
            $timerLabel = 'Days Remaining';

            if ($activeCycle) {
                if ($activeCycle->filing_end && $now->lt($activeCycle->filing_end)) {
                    $targetDate = $activeCycle->filing_end;
                    $timerLabel = 'Filing Ends In';
                } elseif ($activeCycle->voting_start && $now->lt($activeCycle->voting_start)) {
                    $targetDate = $activeCycle->voting_start;
                    $timerLabel = 'Voting Starts In';
                } elseif ($activeCycle && $activeCycle->filing_start && $activeCycle->voting_end && now()->between($activeCycle->filing_start, $activeCycle->voting_end)) {
                    $targetDate = $activeCycle->voting_end;
                    $timerLabel = 'Election Ends In';
                } else {
                    $targetDate = $activeCycle->results_date;
                    $timerLabel = 'Results In';
                }
            }

            $departments = Course::pluck('name')->toArray();
            $allCandidates = Candidate::with(['student', 'position'])
                ->where('election_cycle_id', $activeCycleId)
                ->withCount('votes')
                ->get();

            $tallyByDept = [];
            foreach ($departments as $dept) {
                $tallyByDept[$dept] = $allCandidates
                    ->filter(fn($candidate) => optional(optional($candidate->student)->block)->course->name === $dept)
                    ->map(
                        fn($candidate) => [
                            'label' => "{$candidate->student->first_name} {$candidate->student->last_name}",
                            'position' => $candidate->position->name ?? 'N/A',
                            'votes' => $candidate->votes_count,
                        ],
                    )
                    ->values();
            }

            $totalStudents = Student::count();
            $totalVotes = Vote::whereHas('candidate', function ($q) use ($activeCycleId) {
                $q->where('election_cycle_id', $activeCycleId);
            })
                ->distinct('student_id')
                ->count('student_id');

            $trends = Vote::whereHas('candidate', fn($q) => $q->where('election_cycle_id', $activeCycleId))
                ->where('created_at', '>=', now()->subHours(6))
                ->get()
                ->groupBy(fn($vote) => $vote->created_at->format('H'))
                ->map(function ($votes, $hour) {
                    return [
                        'hour' => (int) $hour . ':00',
                        'count' => $votes->count(),
                    ];
                })
                ->sortBy('hour')
                ->values();

            $yearLevelData = Student::join('blocks', 'students.block_id', '=', 'blocks.id')
                ->join('votes', 'students.id', '=', 'votes.student_id')
                ->join('candidates', 'votes.candidate_id', '=', 'candidates.id')
                ->where('candidates.election_cycle_id', $activeCycleId)
                ->selectRaw('blocks.year_level, COUNT(DISTINCT students.id) as total')
                ->whereIn('blocks.year_level', [1, 2, 3])
                ->groupBy('blocks.year_level')
                ->orderBy('blocks.year_level', 'asc')
                ->get();

            $partyPerformance = Candidate::query()
                ->where('election_cycle_id', $activeCycleId)
                ->withCount('votes')
                ->get()
                ->groupBy('party_name')
                ->map(function ($candidates, $partyName) {
                    return [
                        'party' => $partyName ?: 'Independent',
                        'votes' => $candidates->sum('votes_count'),
                    ];
                })
                ->values()
                ->sortByDesc('votes');

            $courseData = Course::join('blocks', 'courses.id', '=', 'blocks.course_id')->join('students', 'blocks.id', '=', 'students.block_id')->join('votes', 'students.id', '=', 'votes.student_id')->join('candidates', 'votes.candidate_id', '=', 'candidates.id')->where('candidates.election_cycle_id', $activeCycleId)->selectRaw('courses.name as course_name, COUNT(votes.id) as vote_count')->groupBy('courses.name')->get();

            return [
                'totalVotes' => $totalVotes,
                'candidatesCount' => $allCandidates->count(),
                'turnout' => $totalStudents > 0 ? number_format(($totalVotes / $totalStudents) * 100, 1) . '%' : '0%',
                'tallyByDept' => $tallyByDept,
                'departments' => $departments,
                'targetDate' => $targetDate ? $targetDate->format('Y-m-d H:i:s') : null,
                'timerLabel' => $timerLabel,
                'endTime' => $activeCycle?->voting_end?->format('Y-m-d H:i:s'),
                'trends' => $trends,
                'yearLevel' => [
                    'labels' => $yearLevelData->pluck('year_level')->map(fn($l) => 'Year ' . $l),
                    'values' => $yearLevelData->pluck('total'),
                ],
                'partyPerformance' => $partyPerformance,
                'courseData' => [
                    'labels' => $courseData->pluck('course_name'),
                    'values' => $courseData->pluck('vote_count'),
                ],
            ];
        });
    }

    #[On('echo:election-results,VoteUpdated')]
    public function refreshAdminStats()
    {
        $activeCycle = $this->active;
        $cacheKey = 'admin_dashboard_data_' . ($activeCycle ? $activeCycle->id : 'none');

        cache()->forget($cacheKey);

        $data = $this->getDashboardData();

        $this->dispatch('update-admin-charts', [
            'tally' => $data['tallyByDept'],
            'totalVotes' => $data['totalVotes'],
            'turnout' => (float) str_replace('%', '', $data['turnout']),
            'trends' => $data['trends'],
            'yearLevel' => $data['yearLevel'],
            'partyPerformance' => $data['partyPerformance'],
            'courseData' => $data['courseData'],
        ]);
    }

    public function with(): array
    {
        return $this->getDashboardData();
    }

    public function downloadReport()
    {
        $data = $this->getDashboardData();
        $data['date'] = now()->format('F d, Y h:i A');

        $admin = Auth::user();
        $data['admin_name'] = $admin->name;

        $data['fingerprint'] = hash('sha256', $data['totalVotes'] . now()->timestamp . $admin->id);

        $pdf = Pdf::loadView('pdf.election-report', $data)->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Election_Report_' . now()->format('Y-m-d') . '.pdf');
    }
}; ?>

<div>
    @include('layouts.partials.admin-sidebar')

    <main class="main-content" x-data="{
        expiry: '{{ $endTime }}',
        remaining: { d: 0 },
        updateCountdown() {
            if (!this.expiry) return;
            let diff = new Date(this.expiry).getTime() - new Date().getTime();
            this.days = diff > 0 ? Math.ceil(diff / (1000 * 60 * 60 * 24)) : 0;
        }
    }" x-init="updateCountdown();
    setInterval(() => updateCountdown(), 60000)">

        <div class="topbar">
            <div class="topbar-info">
                <h2 class="fw-bold text-primary">Admin <span class="text-accent">Dashboard</span></h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Live Election Analytics & Overview</p>
            </div>
            <div>
                @php
                    $active = $this->active;
                    $now = now();
                    $isFinished = $active && $now->gt($active->voting_end);
                    $isOngoing =
                        $active &&
                        $active->filing_start &&
                        $active->voting_end &&
                        $now->between($active->filing_start, $active->voting_end);
                @endphp

                @if ($isFinished)
                    <button type="button" class="btn-glow d-flex align-items-center justify-content-center w-100 py-2"
                        wire:click="downloadReport" wire:loading.attr="disabled">
                        <span wire:loading wire:target="downloadReport" class="spinner-border spinner-border-sm"></span>
                        <i wire:loading.remove wire:target="downloadReport"
                            class="bi bi-file-earmark-pdf d-md-block fs-5 p-2"></i>
                        <span class="fw-bold d-none d-md-inline ms-2" style="font-size: 12px;">Download Result</span>
                    </button>
                @elseif ($isOngoing)
                    <div class="btn-glow d-flex align-items-center justify-content-center w-[80px] md:w-[200px] mb-2 md:mb-0 p-2 md:p-3 bg-success text-white rounded-pill"
                        style="height: 38px; border-radius: 8px; cursor: default;" title="Election Process Ongoing">
                        <i class="bi bi-clock-history fs-7 p-1"></i>
                        <span class="hidden md:inline ms-1" style="font-size: 14px;">Ongoing Election</span>
                    </div>
                @else
                    <button type="button" class="btn btn-light d-flex align-items-center justify-content-center w-100"
                        style="cursor: not-allowed; border: 1px dashed #ccc; opacity: 0.6;" disabled>
                        <i class="bi bi-lock-fill fs-5"></i>
                        <span class="fw-bold d-none d-md-inline ms-2" style="font-size: 12px;">No Active Cycle</span>
                    </button>
                @endif
            </div>
        </div>

        <div class="row g-2 g-lg-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card p-3 p-md-3 shadow-sm h-100" x-data="{
                    current: 0,
                    target: {{ $totalVotes }},
                    animate() {
                        let start = null;
                        const duration = 800;
                        const step = (timestamp) => {
                            if (!start) start = timestamp;
                            const progress = Math.min((timestamp - start) / duration, 1);
                            this.current = Math.floor(progress * this.target);
                            if (progress < 1) window.requestAnimationFrame(step);
                        };
                        window.requestAnimationFrame(step);
                    }
                }" x-init="animate();"
                    @update-admin-charts.window="target = $event.detail.totalVotes; animate();">
                    <div class="stat-icon bg-primary-soft text-primary small"><i class="bi bi-box-seam"></i></div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-dark" x-text="current">0</div>
                    <div class="stat-label small text-muted text-truncate">Total Voters</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card p-3 p-md-3 shadow-sm h-100" x-data="{ current: 0, target: {{ $candidatesCount }} }" x-init="let start = null;
                const step = (ts) => {
                    if (!start) start = ts;
                    let progress = Math.min((ts - start) / 1000, 1);
                    current = Math.floor(progress * target);
                    if (progress < 1) window.requestAnimationFrame(step);
                };
                window.requestAnimationFrame(step);">
                    <div class="stat-icon bg-indigo-soft text-accent small"><i class="bi bi-people"></i></div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-dark" x-text="current">0</div>
                    <div class="stat-label small text-muted text-truncate">Total Candidates</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card p-3 p-md-3 shadow-sm h-100" x-data="{
                    current: 0,
                    target: {{ (float) str_replace('%', '', $turnout) }},
                    animate() {
                        let start = null;
                        const step = (ts) => {
                            if (!start) start = ts;
                            let progress = Math.min((ts - start) / 800, 1);
                            this.current = (progress * this.target).toFixed(1);
                            if (progress < 1) window.requestAnimationFrame(step);
                        };
                        window.requestAnimationFrame(step);
                    }
                }" x-init="animate()"
                    @update-admin-charts.window="target = $event.detail.turnout; animate();">
                    <div class="stat-icon bg-success-soft text-success small"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-success"><span x-text="current">0</span>%</div>
                    <div class="stat-label small text-muted text-truncate">Voter Turnout</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card p-3 p-md-3 shadow-sm h-100" x-data="{
                    remainingSeconds: {{ $targetDate ? now()->diffInSeconds($targetDate, false) : -1 }},
                    displayValue: '...',
                
                    updateTimer() {
                        if (this.remainingSeconds <= 0) {
                            this.displayValue = 'Closed';
                            return;
                        }
                
                        let s = this.remainingSeconds;
                        let d = Math.floor(s / (24 * 3600));
                        s %= (24 * 3600);
                        let h = Math.floor(s / 3600);
                        s %= 3600;
                        let m = Math.floor(s / 60);
                        let sc = s % 60;
                
                        this.displayValue = `${String(d).padStart(2, '0')}d ${String(h).padStart(2, '0')}h ${String(m).padStart(2, '0')}m`;
                    }
                }" x-init="updateTimer();
                setInterval(() => {
                    if (remainingSeconds > 0) {
                        remainingSeconds--;
                        updateTimer();
                    }
                }, 1000);">

                    <div class="stat-icon bg-warning-soft text-warning small">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-warning" style="color: #f59e0b !important;">
                        <span x-text="displayValue">00d 00h 00m</span>
                    </div>
                    <div class="stat-label small text-muted text-truncate">{{ $timerLabel }}</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-lg-4">
                <div class="glass-card p-4 border-0 shadow-sm h-100">
                    <h5 class="fw-bold text-primary mb-1">Voter Turnout Trends</h5>
                    <p class="text-muted small mb-3">Activity over the last 6 hours</p>
                    <div id="chart-trends" wire:ignore style="height: 300px;"></div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="glass-card p-4 border-0 shadow-sm h-100">
                    <h5 class="fw-bold text-primary">Campaign Effectiveness</h5>
                    <div id="chart-party" wire:ignore style="height: 300px;"></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="glass-card p-4 border-0 shadow-sm h-100" x-data="{ activeTab: 'year' }">
                    <div class="d-flex justify-content-between mb-3">
                        <div>
                            <h5 class="fw-bold text-primary mb-0" x-show="activeTab === 'year'">Year Level Breakdown
                            </h5>
                            <h5 class="fw-bold text-primary mb-0" x-show="activeTab === 'course'">Course Participation
                            </h5>
                        </div>
                        <div class="d-flex gap-2">
                            <button @click="activeTab = 'year'"
                                :class="activeTab === 'year' ? 'btn-primary' : 'btn-outline-primary'"
                                class="btn btn-sm py-1 px-2" style="font-size: 0.75rem;">Year</button>
                            <button @click="activeTab = 'course'"
                                :class="activeTab === 'course' ? 'btn-primary' : 'btn-outline-primary'"
                                class="btn btn-sm py-1 px-2" style="font-size: 0.75rem;">Course</button>
                        </div>
                    </div>

                    <div x-show="activeTab === 'year'" wire:ignore>
                        <div id="chart-year-level" style="height: 300px;"></div>
                    </div>
                    <div x-show="activeTab === 'course'" wire:ignore>
                        <div id="chart-course" style="height: 300px;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            @foreach ($departments as $dept)
                <div class="col-lg-6">
                    <div class="glass-card p-4 border-0 shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <h5 class="fw-bold text-primary mb-1">{{ $dept }} Department</h5>
                                <p class="text-muted small mb-0">Live vote distribution</p>
                            </div>
                            <span class="badge-status-live"><span class="pulse-dot"></span> Live Vote Tallying</span>
                        </div>
                        <div style="height: 300px;" wire:ignore>
                            <div id="chart-{{ $dept }}"></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </main>

    @script
        <script>
            let charts = {};
            let trendChart, yearChart;

            const renderCharts = (newData = null) => {
                const data = newData ? newData : @json($this->getDashboardData());
                const tallyData = data.tallyByDept || data;
                const departments = @json($departments);

                departments.forEach(dept => {
                    const container = document.getElementById(`chart-${dept}`);
                    if (!container) return;
                    const d = tallyData[dept];
                    const labels = d.map(item => item.label);
                    const votes = d.map(item => item.votes);

                    if (charts[dept]) {
                        charts[dept].updateSeries([{
                            data: votes
                        }]);
                        charts[dept].updateOptions({
                            xaxis: {
                                categories: labels
                            }
                        });
                    } else {
                        charts[dept] = new ApexCharts(container, {
                            chart: {
                                type: 'bar',
                                height: 300,
                                toolbar: {
                                    show: false
                                }
                            },
                            series: [{
                                name: 'Votes',
                                data: votes
                            }],
                            xaxis: {
                                categories: labels,
                                labels: {
                                    style: {
                                        fontSize: '10px'
                                    }
                                }
                            },
                            colors: ['#3b82f6']
                        });
                        charts[dept].render();
                    }
                });

                const courseContainer = document.querySelector("#chart-course");
                if (courseContainer) {
                    if (charts.course) {
                        charts.course.updateSeries([{
                            data: data.courseData.values
                        }]);
                        charts.course.updateOptions({
                            xaxis: {
                                categories: data.courseData.labels
                            }
                        });
                    } else {
                        charts.course = new ApexCharts(courseContainer, {
                            chart: {
                                type: 'bar',
                                height: 250,
                                toolbar: {
                                    show: false
                                }
                            },
                            series: [{
                                name: 'Votes',
                                data: data.courseData.values
                            }],
                            xaxis: {
                                categories: data.courseData.labels
                            },
                            colors: ['#6366f1']
                        });
                        charts.course.render();
                    }
                }

                const partyContainer = document.querySelector("#chart-party");
                if (partyContainer) {
                    const performanceArray = Object.values(data.partyPerformance);

                    const seriesData = performanceArray.map(p => Number(p.votes));
                    const labelsData = performanceArray.map(p => p.party);

                    if (window.partyChart) {
                        window.partyChart.updateOptions({
                            series: seriesData,
                            labels: labelsData
                        });
                    } else {
                        window.partyChart = new ApexCharts(partyContainer, {
                            chart: {
                                type: 'pie',
                                height: 300,
                                width: '100%'
                            },
                            series: seriesData,
                            labels: labelsData,
                            legend: {
                                position: 'bottom'
                            },
                            colors: ['#3b82f6', '#10B981', '#f59e0b', '#ef4444'],
                            responsive: [{
                                breakpoint: 480,
                                options: {
                                    chart: {
                                        width: '100%'
                                    },
                                    legend: {
                                        position: 'bottom'
                                    }
                                }
                            }]
                        });
                        window.partyChart.render();
                    }
                }

                const trendContainer = document.querySelector("#chart-trends");
                if (trendContainer) {
                    if (trendChart) {
                        trendChart.updateSeries([{
                            data: data.trends.map(t => t.count)
                        }]);
                    } else {
                        trendChart = new ApexCharts(trendContainer, {
                            chart: {
                                type: 'line',
                                height: 300,
                                toolbar: {
                                    show: false
                                }
                            },
                            stroke: {
                                curve: 'smooth',
                                width: 3
                            },
                            series: [{
                                name: 'Votes',
                                data: data.trends.map(t => t.count)
                            }],
                            xaxis: {
                                categories: data.trends.map(t => t.hour)
                            },
                            colors: ['#10B981']
                        });
                        trendChart.render();
                    }
                }

                const yearContainer = document.querySelector("#chart-year-level");
                if (yearContainer) {
                    if (yearChart) {
                        yearChart.updateSeries(data.yearLevel.values);
                    } else {
                        yearChart = new ApexCharts(yearContainer, {
                            chart: {
                                type: 'donut',
                                height: 300
                            },
                            series: data.yearLevel.values,
                            labels: data.yearLevel.labels,
                            legend: {
                                position: 'bottom'
                            },
                            colors: ['#3b82f6', '#6366f1', '#a855f7', '#ec4899'],
                            plotOptions: {
                                pie: {
                                    donut: {
                                        size: '65%'
                                    }
                                }
                            }
                        });
                        yearChart.render();
                    }
                }
            };

            renderCharts();
            $wire.on('update-admin-charts', (payload) => {
                renderCharts(Array.isArray(payload) ? payload[0] : payload);

                setTimeout(() => {
                    if (typeof yearChart !== 'undefined' && yearChart) {
                        yearChart.updateOptions({
                            chart: {
                                width: '100%'
                            }
                        });
                    }
                    if (charts.course) {
                        charts.course.updateOptions({
                            chart: {
                                width: '100%'
                            }
                        });
                    }
                }, 100);
            });
        </script>
    @endscript
</div>

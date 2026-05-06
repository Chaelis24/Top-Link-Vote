<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, On};
use Illuminate\Support\Facades\{Auth, Session};
use App\Models\{Vote, Candidate, Student, ElectionCycle};
use Barryvdh\DomPDF\Facade\Pdf;

new #[Layout('layouts.admin'), Title('Admin Dashboard')] class extends Component {
    private function getDashboardData(): array
    {
        $activeCycle = ElectionCycle::where('status', 'active')->first();
        $now = now();
        $targetDate = null;
        $timerLabel = 'Days Remaining';

        if ($activeCycle) {
            if ($now->lt($activeCycle->filing_end)) {
                $targetDate = $activeCycle->filing_end;
                $timerLabel = 'Filing Ends In';
            } elseif ($now->lt($activeCycle->voting_start)) {
                $targetDate = $activeCycle->voting_start;
                $timerLabel = 'Voting Starts In';
            } elseif ($now->lt($activeCycle->voting_end)) {
                $targetDate = $activeCycle->voting_end;
                $timerLabel = 'Election Ends In';
            } else {
                $targetDate = $activeCycle->results_date;
                $timerLabel = 'Results In';
            }
        }

        $departments = ['IT', 'HRMT', 'ECT', 'HST'];

        $allCandidates = Candidate::whereHas('student', function ($query) use ($departments) {
            $query->whereIn('course', $departments);
        })
            ->with(['student', 'position'])
            ->withCount('votes')
            ->get();

        $tallyByDept = [];
        foreach ($departments as $dept) {
            $tallyByDept[$dept] = $allCandidates
                ->filter(fn($candidate) => $candidate->student->course === $dept)
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
        $totalVotes = Vote::distinct('student_id')->count('student_id');

        return [
            'totalVotes' => $totalVotes,
            'candidatesCount' => Candidate::count(),
            'turnout' => $totalStudents > 0 ? number_format(($totalVotes / $totalStudents) * 100, 1) . '%' : '0%',
            'tallyByDept' => $tallyByDept,
            'departments' => $departments,
            'targetDate' => $targetDate ? $targetDate->toIso8601String() : null,
            'timerLabel' => $timerLabel,
            'endTime' => $activeCycle?->voting_end?->toIso8601String(),
        ];
    }

    #[On('echo:election-results,VoteUpdated')]
    public function refreshAdminStats()
    {
        $data = $this->getDashboardData();

        $this->dispatch('update-admin-charts', tally: $data['tallyByDept'], totalVotes: $data['totalVotes'], turnout: (float) str_replace('%', '', $data['turnout']));
    }

    public function with(): array
    {
        return $this->getDashboardData();
    }

    public function downloadReport()
    {
        $data = $this->getDashboardData();
        $data['date'] = now()->format('F d, Y h:i A');

        $pdf = Pdf::loadView('pdf.election-report', $data);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'Election_Report_' . now()->format('Y-m-d') . '.pdf');
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
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Election Management & Real-time Analytics</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button type="button" class="btn-glow d-flex align-items-center justify-content-center"
                    wire:loading.attr="disabled"
                    x-on:click="
                        Swal.fire({
                            title: 'Generate Report?',
                            text: 'This will compile all election data into a downloadable file.',
                            icon: 'info',
                            showCancelButton: true,
                            confirmButtonColor: '#4f46e5',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Yes, Generate',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $wire.downloadReport()
                            }
                        })
                    "
                    title="Download Election Report">
                    <span wire:loading wire:target="downloadReport" class="me-2">
                        <i class="spinner-border spinner-border-sm"></i>
                    </span>
                    <span wire:loading.remove wire:target="downloadReport" class="me-2">
                        <i class="bi bi-file-earmark-pdf"></i>
                    </span>
                    <span>Election Report</span>
                </button>
            </div>
        </div>

        <div class="row g-2 g-lg-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card p-2 p-md-3 shadow-sm h-100" x-data="{
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

                    <div class="stat-icon bg-primary-soft text-primary small">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-dark" x-text="current">0</div>
                    <div class="stat-label small text-muted text-truncate">Total Voters</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card p-2 p-md-3 shadow-sm h-100" x-data="{ current: 0, target: {{ $candidatesCount }} }" x-init="let start = null;
                const step = (ts) => {
                    if (!start) start = ts;
                    let progress = Math.min((ts - start) / 1000, 1);
                    current = Math.floor(progress * target);
                    if (progress < 1) window.requestAnimationFrame(step);
                };
                window.requestAnimationFrame(step);">
                    <div class="stat-icon bg-indigo-soft text-accent small"
                        style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-dark" x-text="current">0</div>
                    <div class="stat-label small text-muted text-truncate" style="font-size: 0.75rem;">Total Candidates
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card p-2 p-md-3 shadow-sm h-100" x-data="{
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
                    <div class="stat-icon bg-success-soft text-success small">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-success">
                        <span x-text="current">0</span>%
                    </div>
                    <div class="stat-label small text-muted text-truncate">Voter Turnout</div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card p-2 p-md-3 shadow-sm h-100" x-data="{
                    targetDate: '{{ $targetDate }}',
                    displayValue: '0',
                    updateTimer() {
                        if (!this.targetDate) return;
                        let diff = new Date(this.targetDate) - new Date();
                        if (diff > 0) {
                            let d = Math.floor(diff / (1000 * 60 * 60 * 24));
                            let h = Math.floor((diff / (1000 * 60 * 60)) % 24);

                            let dayStr = String(d).padStart(2, '0') + 'd';
                            let hourStr = String(h).padStart(2, '0') + 'h';

                            this.displayValue = `${dayStr} ${hourStr}`;
                        } else {
                            this.displayValue = 'Closed';
                        }
                    }
                }" x-init="updateTimer();
                setInterval(() => updateTimer(), 60000)">

                    <div class="stat-icon bg-warning-soft text-warning small"
                        style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-warning" style="color: #f59e0b !important;">
                        <span x-text="displayValue">00d 00h</span>
                    </div>
                    <div class="stat-label small text-muted text-truncate" style="font-size: 0.75rem;">
                        {{ $timerLabel }}
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
                            <span class="badge-status-live">
                                <span class="pulse-dot"></span> Live Vote Tallying
                            </span>
                        </div>
                        <div style="height: 300px;" wire:ignore>
                            <canvas id="chart-{{ $dept }}"></canvas>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </main>

    @script
        <script>
            let charts = {};

            const renderCharts = (newTallyData = null) => {
                const tallyData = newTallyData ? newTallyData : @json($this->getDashboardData()['tallyByDept']);
                const departments = @json($departments);

                departments.forEach(dept => {
                    const ctx = document.getElementById(`chart-${dept}`);
                    if (!ctx) return;

                    const data = tallyData[dept];
                    const labels = data.map(item => item.label.split(' ')[0]);
                    const fullLabels = data.map(item => `${item.label} (${item.position})`);
                    const votes = data.map(item => item.votes);

                    if (charts[dept]) {
                        charts[dept].data.labels = labels;
                        charts[dept].data.datasets[0].data = votes;
                        charts[dept].update();
                    } else {
                        charts[dept] = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Votes',
                                    data: votes,
                                    backgroundColor: '#3b82f6',
                                    borderRadius: 6,
                                    barThickness: 35
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                animation: {
                                    duration: 1500,
                                    easing: 'easeOutQuart'
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            title: (items) => fullLabels[items[0].dataIndex]
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: {
                                            display: false
                                        },
                                        ticks: {
                                            font: {
                                                size: 10
                                            }
                                        }
                                    },
                                    y: {
                                        beginAtZero: true,
                                        grid: {
                                            color: '#f1f5f9'
                                        },
                                        ticks: {
                                            stepSize: 1,
                                            precision: 0
                                        }
                                    }
                                }
                            }
                        });
                    }
                });
            };

            renderCharts();

            $wire.on('update-admin-charts', (payload) => {
                const eventData = Array.isArray(payload) ? payload[0] : payload;
                renderCharts(eventData.tally);
            });

            document.addEventListener('livewire:navigated', () => {
                Object.values(charts).forEach(chart => chart.destroy());
                charts = {};
                renderCharts();
            });
        </script>
    @endscript
</div>

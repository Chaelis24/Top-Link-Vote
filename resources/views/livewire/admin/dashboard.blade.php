<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title};
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
        $totalVotes = Vote::count();

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
            <div>
                <h2 class="fw-bold text-primary">Admin <span class="text-accent">Dashboard</span></h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Election Management & Real-time Analytics</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button wire:click="downloadReport" wire:loading.attr="disabled" class="btn-glow"
                    title="Download Election Report">
                    <span wire:loading>
                        <i class="spinner-border spinner-border-sm"></i>
                    </span>
                    <span wire:loading.remove>
                        <i class="bi bi-file-earmark-pdf"></i>
                        <span class="d-none d-md-inline ms-1">Download Election Report</span>
                    </span>
                </button>
            </div>
        </div>

        <!-- Stats Cards Section -->
        <div class="row g-2 g-lg-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="stat-card p-2 p-md-3 shadow-sm h-100">
                    <div class="stat-icon bg-primary-soft text-primary small"
                        style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-dark">{{ number_format($totalVotes) }}</div>
                    <div class="stat-label small text-muted text-truncate" style="font-size: 0.75rem;">Total Votes Cast
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card p-2 p-md-3 shadow-sm h-100">
                    <div class="stat-icon bg-indigo-soft text-accent small"
                        style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                        <i class="bi bi-people"></i>
                    </div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-dark">{{ $candidatesCount }}</div>
                    <div class="stat-label small text-muted text-truncate" style="font-size: 0.75rem;">Total Candidates
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card p-2 p-md-3 shadow-sm h-100">
                    <div class="stat-icon bg-success-soft text-success small"
                        style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div class="stat-value mt-1 fs-5 fw-bold text-success" style="color: #10b981 !important;">
                        {{ $turnout }}
                    </div>
                    <div class="stat-label small text-muted text-truncate" style="font-size: 0.75rem;">Voter Turnout
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-3">
                <div class="stat-card p-2 p-md-3 shadow-sm h-100" x-data="{
                    target: '{{ $targetDate }}',
                    displayValue: '0',
                    updateTimer() {
                        if (!this.target) return;
                        let diff = new Date(this.target) - new Date();
                        if (diff > 0) {
                            let d = Math.floor(diff / (1000 * 60 * 60 * 24));
                            let h = Math.floor((diff / (1000 * 60 * 60)) % 24);
                            this.displayValue = (d >= 1) ? Math.ceil(diff / (1000 * 60 * 60 * 24)) + 'd' : h + 'h';
                        } else {
                            this.displayValue = '0';
                        }
                    }
                }" x-init="updateTimer();
                setInterval(() => updateTimer(), 60000)">

                    <div class="stat-icon bg-warning-soft text-warning small"
                        style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                        <i class="bi bi-calendar-event"></i>
                    </div>

                    <div class="stat-value mt-1 fs-5 fw-bold text-warning" style="color: #f59e0b !important;">
                        <span x-text="displayValue">0</span>
                    </div>

                    <div class="stat-label small text-muted text-truncate" style="font-size: 0.75rem;">
                        {{ $timerLabel }}</div>
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
                                <span class="pulse-dot"></span> LIVE
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
            const renderCharts = () => {
                const tallyData = @json($tallyByDept);
                const departments = @json($departments);

                departments.forEach(dept => {
                    const ctx = document.getElementById(`chart-${dept}`);
                    if (!ctx) return;
                    const existingChart = Chart.getChart(ctx);
                    if (existingChart) existingChart.destroy();

                    const data = tallyData[dept];
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.map(item => `${item.label} (${item.position})`),
                            datasets: [{
                                data: data.map(item => item.votes),
                                backgroundColor: '#3b82f6',
                                borderRadius: 6,
                                barThickness: 25
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    backgroundColor: '#1e293b',
                                    padding: 12
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        color: '#f1f5f9'
                                    },
                                    ticks: {
                                        color: '#94a3b8'
                                    }
                                },
                                y: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: '#475569',
                                        font: {
                                            weight: '600',
                                            size: 11
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
            };
            renderCharts();
            document.addEventListener('livewire:navigated', renderCharts);
        </script>
    @endscript
</div>

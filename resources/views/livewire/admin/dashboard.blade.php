<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Vote;
use App\Models\Candidate;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;

new #[Layout('layouts.app'), Title('Admin Dashboard')] class extends Component {
    public function with(): array
    {
        $departments = ['IT', 'HRMT', 'ECT', 'HST'];
        $tallyByDept = [];

        foreach ($departments as $dept) {
            $tallyByDept[$dept] = Candidate::where('course', $dept)
                ->with(['student', 'position'])
                ->withCount('votes')
                ->get()
                ->map(function ($candidate) {
                    return [
                        'label' => $candidate->student->first_name . ' ' . $candidate->student->last_name,
                        'position' => $candidate->position->name ?? 'N/A',
                        'votes' => $candidate->votes_count,
                    ];
                });
        }

        $totalStudents = Student::count();
        $totalVotes = Vote::count();
        $turnout = $totalStudents > 0 ? number_format(($totalVotes / $totalStudents) * 100, 1) . '%' : '0%';

        return [
            'totalVotes' => $totalVotes,
            'candidatesCount' => Candidate::count(),
            'turnout' => $turnout,
            'daysLeft' => 3,
            'tallyByDept' => $tallyByDept,
            'departments' => $departments,
        ];
    }

    public function downloadReport()
    {
        $data = $this->with();
        $data['date'] = now()->format('F d, Y h:i A');

        $pdf = Pdf::loadView('pdf.election-report', $data);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'Election_Report_' . now()->format('Y-m-d') . '.pdf');
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
        return $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2>Admin <span>Dashboard</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Welcome, Administrator</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                {{-- Gumagana na ang button na ito --}}
                <button wire:click="downloadReport" wire:loading.attr="disabled" class="btn btn-outline-glow btn-sm">
                    <span wire:loading.remove><i class="bi bi-download me-1"></i>Download PDF</span>
                    <span wire:loading><i class="spinner-border spinner-border-sm me-1"></i>Generating...</span>
                </button>
                <a href="{{ url('/admin/settings') }}">
                    <div class="admin-avatar-glow">
                        <i class="bi bi-person-fill text-white"></i>
                    </div>
                </a>
            </div>
        </div>

        {{-- Stat Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="glass-card stat-card">
                    <div class="stat-icon icon-green"><i class="bi bi-person-fill-check"></i></div>
                    <div class="stat-value text-accent">{{ number_format($totalVotes) }}</div>
                    <div class="stat-label">Total Votes</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="glass-card stat-card">
                    <div class="stat-icon icon-purple"><i class="bi bi-people-fill"></i></div>
                    <div class="stat-value text-purple">{{ $candidatesCount }}</div>
                    <div class="stat-label">Candidates</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="glass-card stat-card">
                    <div class="stat-icon icon-green"><i class="bi bi-check-circle-fill"></i></div>
                    <div class="stat-value text-success">{{ $turnout }}</div>
                    <div class="stat-label">Turnout Rate</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="glass-card stat-card">
                    <div class="stat-icon icon-warning"><i class="bi bi-clock-history"></i></div>
                    <div class="stat-value text-warning">{{ $daysLeft }}</div>
                    <div class="stat-label">Days Left</div>
                </div>
            </div>
        </div>

        {{-- Department Charts --}}
        <div class="row g-4 mb-4">
            @foreach ($departments as $dept)
                <div class="col-lg-6">
                    <div class="glass-card p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="text-accent mb-0 fw-bold">{{ $dept }} Department</h5>
                            <span class="badge badge-status bg-purple-soft px-3 py-2">
                                <span class="pulse-dot me-1"></span> LIVE TALLY
                            </span>
                        </div>
                        <div style="height: 400px;" wire:ignore>
                            <canvas id="chart-{{ $dept }}"></canvas>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </main>

    <style>
        .admin-avatar-glow {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--purple));
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 15px rgba(56, 142, 60, 0.3);
        }

        .icon-green {
            background: rgba(56, 142, 60, 0.15);
            color: var(--accent);
        }

        .icon-purple {
            background: rgba(103, 58, 183, 0.15);
            color: var(--purple);
        }

        .icon-warning {
            background: rgba(253, 203, 110, 0.15);
            color: var(--warning);
        }

        .bg-purple-soft {
            background: rgba(103, 58, 183, 0.1);
            color: var(--purple);
            border: 1px solid rgba(103, 58, 183, 0.2);
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 50%;
            display: inline-block;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
            }

            70% {
                transform: scale(1);
                box-shadow: 0 0 0 10px rgba(76, 175, 80, 0);
            }

            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
            }
        }
    </style>

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
                                backgroundColor: 'rgba(103, 58, 183, 0.7)',
                                borderColor: 'rgba(103, 58, 183, 1)',
                                borderWidth: 2,
                                borderRadius: 8,
                                barThickness: 35
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(255,255,255,0.05)'
                                    },
                                    ticks: {
                                        color: 'rgba(255,255,255,0.5)',
                                        stepSize: 1
                                    }
                                },
                                y: {
                                    ticks: {
                                        color: 'rgba(255,255,255,0.9)'
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

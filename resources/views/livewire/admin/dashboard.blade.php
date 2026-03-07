<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Admin Dashboard')] class extends Component {
    // Component State
    public int $totalVotes = 1254;
    public int $candidatesCount = 4;
    public string $turnout = '87%';
    public int $daysLeft = 3;
    public array $chartData = [339, 557, 213, 125];

    /**
     * Handle the admin logout logic.
     */
    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/', navigate: true);
    }

    /**
     * Example method for generating reports
     */
    public function downloadReport()
    {
        // Logic for PDF generation would go here
        session()->flash('message', 'Report generation started...');
    }
}; ?>

<div>
    {{-- Sidebar & Navigation --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        {{-- Top Bar --}}
        <div class="topbar">
            <div>
                <h2>Admin <span>Dashboard</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Welcome, Administrator</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button wire:click="downloadReport" class="btn btn-outline-glow btn-sm">
                    <i class="bi bi-download me-1"></i>Download PDF
                </button>
                <a href="{{ url('/admin/settings') }}">
                    <div class="admin-avatar-glow">
                        <i class="bi bi-person-fill text-white"></i>
                    </div>
                </a>
            </div>
        </div>

        {{-- Quick Stat Cards --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3 fade-in-up delay-1">
                <div class="glass-card stat-card">
                    <div class="stat-icon icon-green"><i class="bi bi-person-fill-check"></i></div>
                    <div class="stat-value text-accent">{{ number_format($totalVotes) }}</div>
                    <div class="stat-label">Total Votes</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up delay-2">
                <div class="glass-card stat-card">
                    <div class="stat-icon icon-purple"><i class="bi bi-people-fill"></i></div>
                    <div class="stat-value text-purple">{{ $candidatesCount }}</div>
                    <div class="stat-label">Candidates</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up delay-3">
                <div class="glass-card stat-card">
                    <div class="stat-icon icon-green"><i class="bi bi-check-circle-fill"></i></div>
                    <div class="stat-value text-success">{{ $turnout }}</div>
                    <div class="stat-label">Turnout Rate</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 fade-in-up delay-4">
                <div class="glass-card stat-card">
                    <div class="stat-icon icon-warning"><i class="bi bi-clock-history"></i></div>
                    <div class="stat-value text-warning">{{ $daysLeft }}</div>
                    <div class="stat-label">Days Left</div>
                </div>
            </div>
        </div>

        {{-- Charts Section --}}
        <div class="row g-3 mb-4">
            <div class="col-lg-8 fade-in-up delay-5">
                <div class="glass-card chart-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="bi bi-lightning-fill text-warning me-2"></i>Elections Cycle</h5>
                        <span class="badge badge-status badge-open">
                            <span class="pulse-dot me-1" style="background: var(--success);"></span> Active
                        </span>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-3">
                            <div class="text-center p-2 glass rounded-3">
                                <div class="fw-bold text-accent">1,254</div>
                                <small class="text-white-50 extra-small">Total Votes</small>
                            </div>
                        </div>
                        {{-- Data points here could be dynamic in the future based on $chartData --}}
                        <div class="col-3">
                            <div class="text-center p-2 glass rounded-3">
                                <div class="fw-bold text-purple">50%</div>
                                <small class="text-white-50 extra-small">Candidate A</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="text-center p-2 glass rounded-3">
                                <div class="fw-bold text-success">40%</div>
                                <small class="text-white-50 extra-small">Candidate B</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="text-center p-2 glass rounded-3">
                                <div class="fw-bold text-warning">10%</div>
                                <small class="text-white-50 extra-small">Others</small>
                            </div>
                        </div>
                    </div>

                    <div style="height: 280px;" wire:ignore>
                        <canvas id="adminBarChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 fade-in-up delay-5">
                <div class="glass-card chart-container h-100">
                    <h5><i class="bi bi-pie-chart-fill text-info me-2"></i>Elections Reports</h5>
                    <div style="height: 220px;" wire:ignore>
                        <canvas id="adminPieChart"></canvas>
                    </div>

                    <div class="mt-3">
                        @foreach ([['A', 'accent', '27%'], ['B', 'purple', '44.4%'], ['C', 'success', '17%'], ['D', 'warning', '10%']] as $item)
                            <div
                                class="d-flex justify-content-between align-items-center py-2 border-bottom border-white-5">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="legend-dot" style="background:var(--{{ $item[1] }});"></span>
                                    <small>Candidate {{ $item[0] }}</small>
                                </div>
                                <small class="fw-semibold"
                                    style="color: var(--{{ $item[1] }});">{{ $item[2] }}</small>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-outline-glow btn-sm flex-fill">Full Report</button>
                        <button class="btn btn-outline-glow btn-sm flex-fill">PDF</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="row g-3">
            <div class="col-md-4">
                <div class="glass-card p-4 action-hover">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon icon-green"><i class="bi bi-person-plus-fill"></i></div>
                        <div>
                            <h6 class="mb-0 fw-semibold">Manage Candidates</h6>
                            <small class="text-white-50">Add/Edit profiles</small>
                        </div>
                    </div>
                    <a href="/admin/candidates" wire:navigate class="btn btn-outline-glow btn-sm w-100">Open
                        Manager</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card p-4 action-hover">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon icon-purple"><i class="bi bi-person-lines-fill"></i></div>
                        <div>
                            <h6 class="mb-0 fw-semibold">Student/Voters</h6>
                            <small class="text-white-50">View voter list</small>
                        </div>
                    </div>
                    <a href="/admin/voters" wire:navigate class="btn btn-outline-glow btn-sm w-100">Manage Voters</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="glass-card p-4 action-hover">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon icon-green" style="color: var(--success);"><i
                                class="bi bi-calendar-check-fill"></i></div>
                        <div>
                            <h6 class="mb-0 fw-semibold">Election Cycle</h6>
                            <small class="text-white-50">Set timeline</small>
                        </div>
                    </div>
                    <button class="btn btn-outline-glow btn-sm w-100">Configure</button>
                </div>
            </div>
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

        .extra-small {
            font-size: 0.7rem;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
        }

        .border-white-5 {
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .action-hover:hover {
            transform: translateY(-5px);
            transition: all 0.3s ease;
        }
    </style>

    @script
        <script>
            // Encapsulating Chart logic
            const initCharts = () => {
                const barCtx = document.getElementById('adminBarChart');
                const pieCtx = document.getElementById('adminPieChart');

                if (barCtx) {
                    new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Candidate A', 'Candidate B', 'Candidate C', 'Candidate D'],
                            datasets: [{
                                data: @js($chartData), // Passing class property to JS
                                backgroundColor: [
                                    'rgba(56, 142, 60, 0.7)', 'rgba(103, 58, 183, 0.7)',
                                    'rgba(76, 175, 80, 0.7)', 'rgba(253, 203, 110, 0.7)'
                                ],
                                borderColor: ['#388e3c', '#673ab7', '#4caf50', '#fdcb6e'],
                                borderWidth: 2,
                                borderRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    grid: {
                                        color: 'rgba(255,255,255,0.05)'
                                    },
                                    ticks: {
                                        color: 'rgba(255,255,255,0.5)'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        color: 'rgba(255,255,255,0.5)'
                                    }
                                }
                            }
                        }
                    });
                }

                if (pieCtx) {
                    new Chart(pieCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['A', 'B', 'C', 'D'],
                            datasets: [{
                                data: [27, 44.4, 17, 10],
                                backgroundColor: ['#388e3c', '#673ab7', '#4caf50', '#fdcb6e'],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                }
            };

            initCharts();

            // Support for SPA navigation
            document.addEventListener('livewire:navigated', () => {
                initCharts();
            });
        </script>
    @endscript
</div>

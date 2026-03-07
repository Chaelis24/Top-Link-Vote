<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Student Dashboard')] class extends Component {
    public $student;
    public $hasVoted = false;

    /**
     * Mount is the class-based equivalent of the initial state logic.
     */
    public function mount()
    {
        $this->student = Auth::user();
    }

    /**
     * The logout logic.
     */
    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    {{-- Sidebar --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    @include('layouts.partials.student-sidebar')

    {{-- Main Content --}}
    <main class="main-content">
        {{-- Top Bar --}}
        <div class="topbar" wire:key="persistent-topbar-header">
            <div>
                <h2>Student <span>Dashboard</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">
                    Welcome back, {{ $student->name ?? 'Student' }}
                </p>
            </div>
            <a href="/students/profile" wire:navigate class="text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-circle overflow-hidden">
                        @if ($photo ?? '')
                            <img src="{{ $photo->temporaryUrl() }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @elseif($profile_photo_path ?? '')
                            <img src="{{ asset('storage/' . $profile_photo_path) }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <i class="bi bi-person-fill text-white"></i>
                        @endif
                    </div>
                </div>
            </a>
        </div>

        {{-- Quick Action Cards Row --}}
        <div class="row g-3 mb-4">
            {{-- Profile Card --}}
            <div class="col-lg-4 col-md-6 fade-in-up delay-1">
                <div class="glass-card p-4 h-100">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon icon-accent">
                            <i class="bi bi-person-lines-fill"></i>
                        </div>
                        <h6 class="mb-0 fw-semibold text-white">Student Profile</h6>
                    </div>
                    <p class="text-white-50 mb-3" style="font-size: 0.85rem;">View your profile information and voting
                        eligibility.</p>
                    <a href="/students/profile" wire:navigate class="btn btn-outline-glow btn-sm">View Profile</a>
                </div>
            </div>

            {{-- Platforms Card --}}
            <div class="col-lg-4 col-md-6 fade-in-up delay-2">
                <div class="glass-card p-4 h-100">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon icon-purple">
                            <i class="bi bi-megaphone-fill"></i>
                        </div>
                        <h6 class="mb-0 fw-semibold text-white">Platforms</h6>
                    </div>
                    <p class="text-white-50 mb-3" style="font-size: 0.85rem;">Read the candidate platforms before
                        casting your vote.</p>
                    <a href="/students/platforms" wire:navigate class="btn btn-outline-glow btn-sm">View Candidates</a>
                </div>
            </div>

            {{-- Vote Now Card --}}
            <div class="col-lg-4 col-md-12 fade-in-up delay-3">
                <div class="glass-card p-4 h-100 highlight-border">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="stat-icon icon-success">
                            <i class="bi bi-check2-square"></i>
                        </div>
                        <h6 class="mb-0 fw-semibold text-white">Cast Your Vote</h6>
                    </div>
                    <p class="text-white-50 mb-3" style="font-size: 0.85rem;">Exercise your right! Cast your vote
                        securely.</p>
                    <a href="/students/cast-vote" wire:navigate class="btn btn-glow btn-sm">Vote Now <i
                            class="bi bi-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

        {{-- Real-time Election Tally (Optional for Student) --}}
        <div class="row g-3">
            <div class="col-lg-7 fade-in-up delay-4">
                <div class="glass-card p-4">
                    <h5 class="text-white mb-4"><i class="bi bi-bar-chart-fill text-accent me-2"></i>Live Election Tally
                    </h5>
                    <div style="height: 300px;" wire:ignore>
                        <canvas id="voteBarChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 fade-in-up delay-5">
                <div class="glass-card p-4">
                    <h5 class="text-white mb-4"><i class="bi bi-pie-chart-fill text-info me-2"></i>Vote Distribution
                    </h5>
                    <div style="height: 300px;" wire:ignore>
                        <canvas id="votePieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @script
        <script>
            const renderCharts = () => {
                const barCtx = document.getElementById('voteBarChart');
                const pieCtx = document.getElementById('votePieChart');

                if (barCtx) {
                    new Chart(barCtx, {
                        type: 'bar',
                        data: {
                            labels: ['Candidate A', 'Candidate B', 'Candidate C', 'Candidate D'],
                            datasets: [{
                                label: 'Votes',
                                data: [339, 557, 213, 125],
                                backgroundColor: ['rgba(56, 142, 60, 0.7)', 'rgba(103, 58, 183, 0.7)',
                                    'rgba(76, 175, 80, 0.7)', 'rgba(253, 203, 110, 0.7)'
                                ],
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
                            labels: ['A (27%)', 'B (44.4%)', 'C (17%)', 'D (10%)'],
                            datasets: [{
                                data: [27, 44.4, 17, 10],
                                backgroundColor: ['rgba(56, 142, 60, 0.8)', 'rgba(103, 58, 183, 0.8)',
                                    'rgba(76, 175, 80, 0.8)', 'rgba(253, 203, 110, 0.8)'
                                ],
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: 'rgba(255,255,255,0.7)',
                                        usePointStyle: true
                                    }
                                }
                            }
                        }
                    });
                }
            };

            renderCharts();
            document.addEventListener('livewire:navigated', renderCharts);
        </script>
    @endscript
</div>

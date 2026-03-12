<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Student Dashboard')] class extends Component {
    public $student;
    public $hasVoted = false;
    public $profile_photo_path;

    #[Computed]
    public function tallyData()
    {
        return \App\Models\Position::with([
            'candidates' => function ($query) {
                $query->orderBy('votes_count', 'desc');
            },
            'candidates.student',
        ])
            ->get()
            ->map(function ($position) {
                return [
                    'id' => $position->id,
                    'name' => $position->name,
                    'labels' => $position->candidates->filter(fn($c) => $c->student !== null)->map(fn($c) => $c->student->last_name)->toArray(),
                    'votes' => $position->candidates->filter(fn($c) => $c->student !== null)->pluck('votes_count')->toArray(),
                ];
            });
    }

    public function mount()
    {
        $user = Auth::user()?->load('student');
        $this->student = $user?->student;

        if ($this->student) {
            $this->profile_photo_path = $this->student->photo ?? ($this->student->profile_photo_path ?? null);
        }
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
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>

    @include('layouts.partials.student-sidebar')

    <main class="main-content">
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
                        @if ($profile_photo_path)
                            <img src="{{ asset('storage/' . $profile_photo_path) }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <i class="bi bi-person-fill text-white"></i>
                        @endif
                    </div>
                </div>
            </a>
        </div>

        <div class="row g-3 mb-4">
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

        <div class="row g-3">
            @foreach ($this->tallyData as $index => $pos)
                <div class="col-lg-6 fade-in-up">
                    <div class="glass-card p-4 mb-3">
                        <h5 class="text-white mb-4">
                            <i class="bi bi-bar-chart-fill text-accent me-2"></i>{{ $pos['name'] }} Tally
                        </h5>
                        <div style="height: 300px;" wire:ignore>
                            <canvas id="chart-{{ $pos['id'] }}"></canvas>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </main>

    @script
        <script>
            const renderCharts = () => {
                const tallyData = @json($this->tallyData);

                tallyData.forEach(pos => {
                    const ctx = document.getElementById(`chart-${pos.id}`);
                    if (!ctx) return;

                    const existingChart = Chart.getChart(ctx);
                    if (existingChart) existingChart.destroy();

                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: pos.labels,
                            datasets: [{
                                label: 'Votes',
                                data: pos.votes,
                                backgroundColor: [
                                    'rgba(103, 58, 183, 0.7)',
                                    'rgba(0, 184, 148, 0.7)',
                                    'rgba(9, 132, 227, 0.7)',
                                    'rgba(253, 203, 110, 0.7)',
                                    'rgba(231, 76, 60, 0.7)'
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
                                    beginAtZero: true,
                                    grid: {
                                        color: 'rgba(255,255,255,0.05)'
                                    },
                                    ticks: {
                                        color: 'rgba(255,255,255,0.5)',
                                        stepSize: 1
                                    }
                                },
                                x: {
                                    ticks: {
                                        color: 'rgba(255,255,255,0.5)'
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

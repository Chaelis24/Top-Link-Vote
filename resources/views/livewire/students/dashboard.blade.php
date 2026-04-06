<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Student Dashboard')] class extends Component {
    public $student;
    public $profile_photo_path;

    #[Computed]
    public function tallyData()
    {
        // Kunin ang course ng current student
        $voterCourse = $this->student->course ?? null;

        return \App\Models\Candidate::with(['student', 'position'])
            ->whereHas('position', function ($query) use ($voterCourse) {
                // I-filter ang positions:
                // 1. Walang department (General) OR
                // 2. Match sa course ng student
                $query->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourse);
            })
            ->get()
            ->map(function ($candidate) {
                return [
                    'label' => ($candidate->student->last_name ?? 'Unknown') . ' (' . ($candidate->position->name ?? 'N/A') . ')',
                    'votes' => $candidate->votes_count,
                ];
            })
            ->sortByDesc('votes')
            ->values()
            ->toArray();
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
            <div class="col-lg-4 col-md-6">
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

            <div class="col-lg-4 col-md-6">
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

            <div class="col-lg-4 col-md-12">
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
            <div class="col-12">
                <div class="glass-card p-4 mb-3">
                    <h5 class="text-white mb-4">
                        <i class="bi bi-bar-chart-line-fill text-accent me-2"></i>Live Election Standings for <span
                            class="text-accent">{{ $student->course }}</span>
                    </h5>
                    <div style="height: 450px;" wire:ignore>
                        <canvas id="mainTallyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @script
        <script>
            const renderChart = () => {
                const data = @json($this->tallyData);
                const ctx = document.getElementById('mainTallyChart');
                if (!ctx) return;

                const existingChart = Chart.getChart(ctx);
                if (existingChart) existingChart.destroy();

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(item => item.label),
                        datasets: [{
                            label: 'Votes',
                            data: data.map(item => item.votes),
                            backgroundColor: 'rgba(103, 58, 183, 0.7)',
                            borderColor: 'rgba(103, 58, 183, 1)',
                            borderWidth: 1,
                            borderRadius: 6
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
                                    color: 'rgba(255,255,255,0.8)'
                                }
                            }
                        }
                    }
                });
            };

            renderChart();
            document.addEventListener('livewire:navigated', renderChart);
        </script>
    @endscript
</div>

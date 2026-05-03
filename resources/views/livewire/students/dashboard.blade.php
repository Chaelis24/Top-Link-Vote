<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Setting;

new #[Layout('layouts.app')] #[Title('Student Dashboard')] class extends Component {
    public $student;
    public $profile_photo_path;

    // Live Control States
    public bool $isVotingOpen = false;
    public bool $isResultsVisible = false;

    #[Computed]
    public function tallyData()
    {
        if (!$this->isResultsVisible) {
            return [];
        }

        $voterCourse = $this->student->course ?? null;

        return \App\Models\Candidate::with(['student', 'position'])
            ->withCount('votes')
            ->whereHas('position', function ($query) use ($voterCourse) {
                $query->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourse);
            })
            ->join('positions', 'candidates.position_id', '=', 'positions.id')
            ->orderBy('positions.priority', 'asc')
            ->orderBy('votes_count', 'desc')
            ->select('candidates.*')
            ->get()
            ->map(function ($candidate) {
                return [
                    'label' => ($candidate->student->last_name ?? 'Unknown') . ' (' . ($candidate->position->name ?? 'N/A') . ')',
                    'votes' => $candidate->votes_count ?? 0,
                ];
            })
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

        $settings = Setting::pluck('value', 'key')->toArray();
        $this->isVotingOpen = (bool) ($settings['allowVoting'] ?? false);
        $this->isResultsVisible = (bool) ($settings['showResults'] ?? false);
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
    @include('layouts.partials.student-sidebar')
    <main class="main-content">
        <div class="topbar" wire:key="persistent-topbar-header">
            <div class="topbar-info">
                <h2 class="mb-0">Student <span class="text-primary">Dashboard</span></h2>
                <p class="text-secondary mb-0" style="font-size: 0.85rem; font-weight: 500;">
                    Welcome back, <span class="text-primary">{{ $student->first_name ?? 'Student' }}
                        {{ $student->middle_name ? substr($student->middle_name, 0, 1) . '.' : 'N/A' }}
                        {{ $student->last_name ?? 'Student' }}
                        {{ $student->suffix ?? '' }}</span>
                </p>
            </div>
        </div>

        <div class="row g-3 mb-2">
            <div class="col-lg-4 col-md-12">
                <div class="glass-card p-2 h-100 border-0 shadow-sm">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <h6 class="mb-0 fw-bold text-primary">Cast Your Vote</h6>
                    </div>

                    @if ($isVotingOpen)
                        <p class="text-secondary mb-4" style="font-size: 0.85rem;">Exercise your right! Cast your vote
                            securely in the system.</p>
                        <a href="/students/cast-vote" wire:navigate class="btn btn-glow btn-sm w-50">
                            Vote Now <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    @else
                        <p class="text-muted mb-4" style="font-size: 0.85rem;">The voting portal is currently
                            <strong>closed</strong> by the administrator.
                        </p>
                        <button
                            class="btn btn-sm w-100 py-2 fw-bold d-flex align-items-center justify-content-center gap-2"
                            style="background-color: #f1f5f9;
                                    color: #64748b;
                                    border: 1px solid #e2e8f0;
                                    cursor: not-allowed;
                                    font-size: 0.7rem;
                                    text-transform: uppercase;
                                    letter-spacing: 0.5px;
                                    border-radius: 8px;
                                    opacity: 0.7;"
                            disabled>
                            <i class="bi bi-shield-lock-fill" style="font-size: 0.85rem; color: #94a3b8;"></i>
                            <span>Portal Locked</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-3 grid grid-cols-1">
            <div class="col-12 w-full">
                <div class="glass-card p-4 md:p-6 mb-3 border-0 shadow-sm">

                    <div class="d-flex flex-col sm:flex-row justify-content-between align-items-center mb-4 gap-3">
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
                            <canvas id="mainTallyChart"></canvas>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <div class="p-3 bg-light d-inline-block rounded-circle mb-3">
                                <i class="bi bi-eye-slash fs-1 text-muted"></i>
                            </div>
                            <h6 class="fw-bold text-dark">Tally is Hidden</h6>
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
            const renderChart = () => {
                const data = @json($this->tallyData);
                const ctx = document.getElementById('mainTallyChart');
                if (!ctx || data.length === 0) return;

                const existingChart = Chart.getChart(ctx);
                if (existingChart) existingChart.destroy();

                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.map(item => item.label),
                        datasets: [{
                            label: 'Votes',
                            data: data.map(item => item.votes),
                            backgroundColor: 'rgba(16, 185, 129, 0.6)',
                            borderColor: '#059669',
                            borderWidth: 2,
                            borderRadius: 8,
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
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: {
                                    color: '#f1f5f9'
                                },
                                ticks: {
                                    color: '#64748b'
                                }
                            },
                            y: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    color: '#1e293b',
                                    font: {
                                        weight: '600'
                                    }
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

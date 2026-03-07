<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Cast Your Vote')] class extends Component {
    public int $currentStep = 1;

    public array $selections = [
        'President' => null,
        'Vice President' => null,
        'Secretary' => null,
        'Treasurer' => null,
    ];

    public array $candidates = [];

    /**
     * Initialize data. In a real app, you would likely fetch
     * these candidates from a database model here.
     */
    public function mount()
    {
        $this->candidates = [
            'President' => [['id' => 1, 'name' => 'Maria Santos', 'party' => 'Unity Party', 'info' => 'BSIT 3rd Year', 'initials' => 'MS', 'bg' => '#388E3C'], ['id' => 2, 'name' => 'Juan Dela Cruz', 'party' => 'Progress Alliance', 'info' => 'BSCS 4th Year', 'initials' => 'JD', 'bg' => '#673AB7']],
            'Vice President' => [['id' => 3, 'name' => 'Anna Reyes', 'party' => 'Unity Party', 'info' => 'BSA 3rd Year', 'initials' => 'AR', 'bg' => '#388E3C'], ['id' => 6, 'name' => 'Mark Garcia', 'party' => 'Progress Alliance', 'info' => 'BSIT 4th Year', 'initials' => 'MG', 'bg' => '#673AB7']],
            'Secretary' => [['id' => 4, 'name' => 'Carlos Ramos', 'party' => 'Progress Alliance', 'info' => 'BSBA 2nd Year', 'initials' => 'CR', 'bg' => '#673AB7']],
            'Treasurer' => [['id' => 5, 'name' => 'Patricia Lim', 'party' => 'Independent', 'info' => 'BSA 3rd Year', 'initials' => 'PL', 'bg' => '#fdcb6e']],
        ];
    }

    /**
     * Logic for navigating between steps with validation.
     */
    public function setStep($step)
    {
        if ($step == 2) {
            $empty = collect($this->selections)->filter(fn($val) => is_null($val));
            if ($empty->count() > 0) {
                $this->dispatch('swal', title: 'Wait!', text: 'Please select candidates for all positions.', icon: 'warning');
                return;
            }
        }
        $this->currentStep = $step;
    }

    /**
     * Submit the vote and redirect.
     */
    public function submitVote()
    {
        // DB Logic Here:
        // Vote::create(['user_id' => auth()->id(), 'votes' => json_encode($this->selections)]);

        $this->dispatch('swal', title: 'Success!', text: 'Your vote has been cast.', icon: 'success');

        return $this->redirect('/student/dashboard', navigate: true);
    }

    /**
     * Logout logic.
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
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.student-sidebar')

    <main class="main-content">
        {{-- Header --}}
        <div class="topbar" wire:key="persistent-topbar-header">
            <div>
                <h2>Cast Your <span>Vote</span></h2>
                <p class="text-white-50 mb-0">Select your preferred candidate for each position</p>
            </div>
            <a href="/students/profile" wire:navigate class="text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-circle">
                            <i class="bi bi-person-fill text-white"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Stepper Progress --}}
        <div class="vote-stepper mb-5 fade-in-up delay-1">
            @foreach ([1 => 'Select', 2 => 'Review', 3 => 'Confirm'] as $num => $label)
                <div class="step {{ $currentStep == $num ? 'active' : ($currentStep > $num ? 'completed' : '') }}">
                    <div class="step-circle">{{ $num }}</div>
                    <span class="step-label">{{ $label }}</span>
                </div>
                @if ($num < 3)
                    <div class="step-line"></div>
                @endif
            @endforeach
        </div>

        {{-- STEP 1: SELECTION --}}
        @if ($currentStep == 1)
            <div class="fade-in">
                @foreach ($candidates as $position => $list)
                    <div class="vote-position-header mb-3 mt-4 d-flex align-items-center gap-2">
                        <i class="bi bi-check2-circle text-accent fs-4"></i>
                        <h5 class="mb-0 fw-bold text-white">{{ $position }}</h5>
                    </div>

                    <div class="row g-3">
                        @foreach ($list as $candidate)
                            <div class="col-md-6">
                                <div class="vote-candidate-option {{ ($selections[$position]['id'] ?? null) == $candidate['id'] ? 'selected' : '' }}"
                                    wire:click="$set('selections.{{ $position }}', {{ json_encode($candidate) }})"
                                    style="cursor: pointer;">
                                    <div class="option-card d-flex align-items-center gap-3 p-3">
                                        <div class="option-avatar d-flex align-items-center justify-content-center fw-bold text-white"
                                            style="background: {{ $candidate['bg'] }}; width: 50px; height: 50px; border-radius: 10px;">
                                            {{ $candidate['initials'] }}
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0 fw-bold text-white">{{ $candidate['name'] }}</h6>
                                            <small class="text-white-50">{{ $candidate['party'] }}</small>
                                        </div>
                                        <div class="check-indicator fs-4">
                                            <i class="bi bi-check-circle-fill"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div class="d-flex justify-content-end mt-5">
                    <button wire:click="setStep(2)" class="btn btn-glow px-4">Review Choices <i
                            class="bi bi-arrow-right ms-2"></i></button>
                </div>
            </div>
        @endif

        {{-- STEP 2: REVIEW --}}
        @if ($currentStep == 2)
            <div class="fade-in">
                <div class="glass-card p-4 mb-4">
                    <h5 class="fw-bold mb-4 text-white"><i class="bi bi-clipboard-check me-2 text-accent"></i>Summary of
                        Your Ballot</h5>
                    <div class="confirm-summary">
                        @foreach ($selections as $pos => $data)
                            <div
                                class="summary-item d-flex justify-content-between align-items-center p-3 mb-2 rounded border border-white-5">
                                <div>
                                    <small class="text-white-50 d-block">{{ $pos }}</small>
                                    <span class="fw-bold {{ $data ? 'text-white' : 'text-warning' }}">
                                        {{ $data['name'] ?? 'Not Selected' }}
                                    </span>
                                </div>
                                <i
                                    class="bi {{ $data ? 'bi-patch-check-fill text-accent' : 'bi-exclamation-triangle text-warning' }} fs-5"></i>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="d-flex justify-content-between">
                    <button wire:click="setStep(1)" class="btn btn-outline-glow px-4">Back</button>
                    <button wire:click="setStep(3)" class="btn btn-glow px-4">Finalize Vote <i
                            class="bi bi-arrow-right ms-2"></i></button>
                </div>
            </div>
        @endif

        {{-- STEP 3: CONFIRMATION --}}
        @if ($currentStep == 3)
            <div class="text-center fade-in">
                <div class="glass-card p-5">
                    <div class="mb-4">
                        <i class="bi bi-shield-lock-fill text-accent" style="font-size: 4rem;"></i>
                    </div>
                    <h3 class="fw-bold text-white mb-3">Confirm Submission</h3>
                    <p class="text-white-50 mb-4">Please double-check everything. Once submitted, <br> <strong
                            class="text-warning">you can no longer change your vote.</strong></p>

                    <div class="d-flex justify-content-center gap-3">
                        <button wire:click="setStep(2)" class="btn btn-outline-glow px-4">Wait, Review Again</button>
                        <button wire:click="submitVote" class="btn btn-glow px-5 py-2">Cast Official Vote</button>
                    </div>
                </div>
            </div>
        @endif
    </main>
</div>

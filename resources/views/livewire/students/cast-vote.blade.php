<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Computed};
use Illuminate\Support\Facades\{Auth, Mail, DB, Session, Log};
use App\Mail\VoteConfirmed;
use App\Models\{Position, Candidate, Vote, ElectionCycle};

new #[Layout('layouts.app')] #[Title('Digital Ballot')] class extends Component {
    public int $currentStep = 1;
    public array $selections = [];

    #[Computed]
    public function hasVoted()
    {
        return auth()->user()->student->has_voted;
    }

    #[Computed]
    public function activeCycle()
    {
        return ElectionCycle::where('is_active', true)->first();
    }

    #[Computed]
    public function electionData()
    {
        if (!$this->activeCycle) {
            return collect();
        }

        $voterCourse = auth()->user()->student->course;

        return Position::where('election_cycle_id', $this->activeCycle->id)
            ->where('is_active', true)
            ->with([
                'candidates' => function ($query) use ($voterCourse) {
                    $query
                        ->where('status', 'approved')
                        ->whereHas('student', function ($q) use ($voterCourse) {
                            $q->where('course', $voterCourse);
                        })
                        ->with('student');
                },
            ])
            ->orderBy('priority', 'asc')
            ->get()
            ->filter(fn($position) => $position->candidates->isNotEmpty());
    }

    public function mount()
    {
        if ($this->hasVoted) {
            return;
        }

        foreach ($this->electionData as $position) {
            $this->selections[$position->id] = null;
        }
    }

    public function setStep($step)
    {
        sleep(1);

        if ($step == 2) {
            if (collect($this->selections)->contains(null)) {
                $this->dispatch('swal:modal', [
                    'type' => 'warning',
                    'title' => 'Incomplete Selection!',
                    'text' => 'Please select candidates for all positions before proceeding.',
                    'theme' => 'dark',
                ]);
                return;
            }
        }

        $this->currentStep = $step;
    }

    public function submitVote()
    {
        $user = auth()->user();
        $student = $user->student;
        $cycle = $this->activeCycle;

        if ($student->has_voted) {
            $this->dispatch('swal', title: 'Error!', text: 'Vote already recorded.', icon: 'error');
            return;
        }

        try {
            DB::transaction(function () use ($student, $cycle, $user) {
                $referenceNumber = 'REF-' . strtoupper(bin2hex(random_bytes(4)));

                foreach ($this->selections as $positionId => $candidateId) {
                    if (!$candidateId) {
                        continue;
                    }

                    $candidate = Candidate::where('id', $candidateId)
                        ->whereHas('student', function ($q) use ($student) {
                            $q->where('course', $student->course);
                        })
                        ->first();

                    if ($candidate) {
                        Vote::create([
                            'student_id' => $student->id,
                            'candidate_id' => $candidateId,
                            'position_id' => $positionId,
                            'election_cycle_id' => $cycle->id,
                            'reference_number' => $referenceNumber,
                            'voted_at' => now(),
                        ]);

                        $candidate->increment('votes_count');
                    }
                }

                $student->update([
                    'has_voted' => true,
                    'voted_at' => now(),
                    'vote_reference' => $referenceNumber,
                ]);

                \App\Models\ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'Voted',
                    'description' => "Cast votes with Reference: $referenceNumber",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);

                try {
                    Mail::to($user->email)->send(new VoteConfirmed($student, $cycle, $referenceNumber));
                } catch (\Exception $e) {
                    Log::error('Mail Error: ' . $e->getMessage());
                }
            });

            $this->dispatch('swal', title: 'Success!', text: 'Your vote has been cast. Check your email for the receipt.', icon: 'success');
            return $this->redirect('/students/cast-vote', navigate: true);
        } catch (\Exception $e) {
            Log::error('Voting Error: ' . $e->getMessage());
            $this->dispatch('swal', title: 'Error!', text: 'Something went wrong.', icon: 'error');
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
    <style>
        .spin-animation {
            display: inline-block;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }
    </style>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.student-sidebar')

    <main class="main-content">
        <div class="topbar" wire:key="persistent-topbar-header">
            <div class="d-flex align-items-center gap-4">
                <div>
                    <h2 class="mb-0">Digital <span>Ballot</span></h2>
                    <p class="text-white-50 mb-0 small">
                        {{ $this->hasVoted ? 'Election Participation Status' : 'Secure Electronic Voting System' }}
                    </p>
                </div>
            </div>

            <a href="/students/profile" wire:navigate class="text-decoration-none">
                <div class="avatar-circle">
                    <i class="bi bi-person-fill text-white"></i>
                </div>
            </a>
        </div>

        @if ($this->hasVoted)
            <div class="fade-in d-flex flex-column align-items-center justify-content-center" style="min-height: 65vh;">
                <div class="glass-card p-5 text-center shadow-lg"
                    style="max-width: 550px; border-top: 4px solid var(--accent-color);">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success"
                            style="font-size: 5rem; filter: drop-shadow(0 0 10px rgba(40, 167, 69, 0.4));"></i>
                    </div>
                    <h2 class="fw-bold text-white mb-2">Vote Submitted!</h2>
                    <p class="text-white-50 mb-4 px-3">
                        Hi <strong>{{ auth()->user()->name }}</strong>, your vote has been securely recorded.
                    </p>
                    <div class="receipt-box p-3 mb-4 text-start rounded bg-white-5 border border-white-10">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-white-50">Status:</small>
                            <span class="badge bg-success">Verified</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-white-50">Date Processed:</small>
                            <span class="text-white small fw-bold">
                                {{ auth()->user()->student->voted_at?->timezone('Asia/Manila')->format('F d, Y - h:i A') }}
                            </span>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <a href="/students/dashboard" wire:navigate class="btn btn-glow py-3">Return to Dashboard</a>
                    </div>
                </div>
            </div>
        @else
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

            @if ($currentStep == 1)
                <div class="fade-in">
                    <div class="text-center mb-4">
                        <span class="badge bg-dark px-4 py-2 rounded-pill border border-white-10">
                            Current Department: <span
                                class="text-accent fw-bold">{{ auth()->user()->student->course }}</span>
                        </span>
                    </div>

                    @foreach ($this->electionData as $position)
                        <div class="vote-position-header mb-3 mt-4">
                            <h5 class="mb-0 fw-bold text-white">{{ $position->name }}</h5>
                        </div>
                        <div class="row g-3">
                            @foreach ($position->candidates as $candidate)
                                <div class="col-md-6">
                                    <div class="vote-candidate-option {{ ($selections[$position->id] ?? null) == $candidate->id ? 'selected' : '' }}"
                                        wire:click="$set('selections.{{ $position->id }}', {{ $candidate->id }})"
                                        style="cursor: pointer;">
                                        <div class="option-card d-flex align-items-center gap-3 p-3">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0 fw-bold text-white">
                                                    {{ $candidate->student->first_name }}
                                                    {{ $candidate->student->last_name }}</h6>
                                                <small class="text-white-50">{{ $candidate->party_name }}</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                    <div class="mt-5 text-end">
                        <button wire:click="setStep(2)" wire:loading.attr="disabled" class="btn btn-glow px-4">
                            <span wire:loading.remove wire:target="setStep(2)">Review Choices <i
                                    class="bi bi-arrow-right ms-2"></i></span>
                            <span wire:loading wire:target="setStep(2)"><i
                                    class="bi bi-arrow-repeat spin-animation me-2"></i>Checking...</span>
                        </button>
                    </div>
                </div>
            @endif

            @if ($currentStep == 2)
                <div class="fade-in text-center mb-5">
                    <h4 class="text-white fw-bold mb-2">Review Your Ballot</h4>
                </div>

                <div class="glass-card p-4 mb-4 border border-white-5 shadow-lg">
                    <h5 class="fw-bold mb-4 text-white"><i class="bi bi-clipboard-check me-2 text-accent"></i>Selection
                        Summary</h5>
                    <div class="confirm-summary">
                        @foreach ($this->electionData as $position)
                            @php
                                $selectedCandidateId = $selections[$position->id] ?? null;
                                $candidate = $position->candidates->firstWhere('id', $selectedCandidateId);
                            @endphp
                            <div class="summary-item d-flex justify-content-between align-items-center p-3 mb-2 rounded border border-white-5"
                                style="background: rgba(255, 255, 255, 0.02);">
                                <div>
                                    <small class="text-white-50 d-block text-uppercase mb-1"
                                        style="font-size: 0.65rem;">{{ $position->name }}</small>
                                    <span class="fw-bold {{ $candidate ? 'text-white' : 'text-danger italic' }}">
                                        {{ $candidate ? $candidate->student->first_name . ' ' . $candidate->student->last_name : 'No Candidate Selected' }}
                                    </span>
                                </div>
                                @if ($candidate)
                                    <i class="bi bi-check-circle-fill text-accent fs-5"></i>
                                @else
                                    <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button wire:click="setStep(1)" class="btn btn-outline-glow px-4">Back</button>
                    <button wire:click="setStep(3)" wire:loading.attr="disabled" class="btn btn-glow px-5">
                        <span wire:loading.remove wire:target="setStep(3)">Proceed to Confirm</span>
                        <span wire:loading wire:target="setStep(3)"><i
                                class="bi bi-shield-lock-fill spin-animation me-2"></i>Proceeding...</span>
                    </button>
                </div>
            @endif

            @if ($currentStep == 3)
                <div class="text-center fade-in">
                    <div class="glass-card p-5 border border-white-5 shadow-lg">
                        <div class="mb-4"><i class="bi bi-shield-lock-fill text-accent" style="font-size: 4rem;"></i>
                        </div>
                        <h3 class="fw-bold text-white mb-3">Final Confirmation</h3>
                        <p class="text-white-50 mb-4 px-md-5">Verify your choices. You cannot undo this action after
                            submission.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <button wire:click="setStep(2)" class="btn btn-outline-glow px-4">Review Again</button>
                            <button wire:click="submitVote" wire:loading.attr="disabled" class="btn btn-glow px-5">
                                <span wire:loading.remove wire:target="submitVote">Cast Official Vote</span>
                                <span wire:loading wire:target="submitVote"><i
                                        class="bi bi-hourglass-split spin-animation me-2"></i>Processing...</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </main>
</div>

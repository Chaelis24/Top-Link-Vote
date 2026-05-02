<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Computed};
use Illuminate\Support\Facades\{Auth, Mail, DB, Session, Log};
use App\Mail\VoteConfirmed;
use App\Models\{Position, Candidate, Vote, ElectionCycle, ActivityLog, Setting};

new #[Layout('layouts.app')] #[Title('Digital Ballot')] class extends Component {
    public $profile_photo_path = '';
    public int $currentStep = 1;
    public array $selections = [];

    public bool $isVotingOpen = false;
    public bool $isMaintenance = false;

    #[Computed]
    public function hasVoted()
    {
        return auth()->user()->student->has_voted;
    }

    #[Computed]
    public function activeCycle()
    {
        return ElectionCycle::where('status', 'active')->first();
    }

    #[Computed]
    public function electionData()
    {
        if (!$this->activeCycle || !$this->isVotingOpen) {
            return collect();
        }

        $voterCourse = auth()->user()->student->course;

        return Position::where('election_cycle_id', $this->activeCycle->id)
            ->where(function ($query) use ($voterCourse) {
                $query->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourse);
            })
            ->with([
                'candidates' => function ($query) use ($voterCourse) {
                    $query
                        ->whereIn('status', ['approved', 'active', 'pending'])
                        ->whereHas('student', function ($q) use ($voterCourse) {
                            $q->where('course', $voterCourse);
                        })
                        ->with('student');
                },
            ])
            ->with(['candidates.student'])
            ->orderBy('priority', 'asc')
            ->get()
            ->filter(fn($position) => $position->candidates->isNotEmpty());
    }

    public function mount()
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $this->isMaintenance = (bool) ($settings['maintenanceMode'] ?? false);
        $this->isVotingOpen = (bool) ($settings['allowVoting'] ?? false) && !$this->isMaintenance;

        if ($this->isMaintenance) {
            $this->dispatch('swal', [
                'title' => 'Maintenance in Progress',
                'text' => 'We are performing essential updates to improve your experience. Please try again later.',
                'icon' => 'warning',
                'confirmButtonColor' => '#3b82f6',
            ]);
        }

        $user = Auth::user()?->load('student');
        $this->student = $user?->student;

        if ($this->student) {
            $this->profile_photo_path = $this->student->photo;
        }

        if ($this->isVotingOpen && !$this->hasVoted) {
            foreach ($this->electionData as $position) {
                $this->selections[$position->id] = null;
            }
        }
    }

    public function setStep($step)
    {
        $this->isVotingOpen = (bool) (Setting::where('key', 'allowVoting')->value('value') ?? false);
        if (!$this->isVotingOpen) {
            return;
        }

        if ($step > $this->currentStep) {
            $unselectedPositions = [];
            foreach ($this->electionData as $position) {
                if (!isset($this->selections[$position->id]) || is_null($this->selections[$position->id])) {
                    $unselectedPositions[] = $position->name;
                }
            }

            if (count($unselectedPositions) > 0) {
                $this->dispatch('swal', [
                    'title' => 'Incomplete Selection',
                    'text' => 'Please select a candidate for: ' . implode(', ', $unselectedPositions),
                    'icon' => 'warning',
                ]);
                return;
            }
        }

        $this->currentStep = $step;
    }

    public function submitVote()
    {
        $isOpen = (bool) (Setting::where('key', 'allowVoting')->value('value') ?? false);
        if (!$isOpen) {
            $this->dispatch('swal', [
                'title' => 'Action Denied',
                'text' => 'The voting portal was just closed.',
                'icon' => 'error',
            ]);
            return $this->redirect('/students/dashboard', navigate: true);
        }

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
                    Vote::create([
                        'student_id' => $student->id,
                        'candidate_id' => $candidateId,
                        'position_id' => $positionId,
                        'election_cycle_id' => $cycle->id,
                        'reference_number' => $referenceNumber,
                        'voted_at' => now(),
                    ]);

                    Candidate::where('id', $candidateId)->increment('votes_count');
                }

                $student->update([
                    'has_voted' => true,
                    'voted_at' => now(),
                    'vote_reference' => $referenceNumber,
                ]);

                ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'Voted',
                    'description' => "Cast votes with Reference: $referenceNumber",
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                try {
                    Mail::to($user->email)->send(new VoteConfirmed($student, $cycle, $referenceNumber));
                } catch (\Exception $e) {
                    Log::error('Mail Error: ' . $e->getMessage());
                }
            });

            $this->dispatch('swal', [
                'title' => 'Success!',
                'text' => 'Your vote has been cast.',
                'icon' => 'success',
            ]);

            return $this->redirect('/students/cast-vote', navigate: true);
        } catch (\Exception $e) {
            Log::error('Voting Error: ' . $e->getMessage());
            $this->dispatch('swal', title: 'Error!', text: 'Something went wrong.', icon: 'error');
        }
    }
    public function getAvatarColor($id)
    {
        $colors = ['#10b981', '#3b82f6', '#6366f1', '#f59e0b', '#ef4444'];
        return $colors[$id % count($colors)];
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
        <div class="topbar">
            <div>
                <h2 class="mb-0 text-dark">Digital <span style="color: #10b981">Ballot</span></h2>
                <p class="text-secondary mb-0 small">Secure Electronic Voting System</p>
            </div>
        </div>

        @if (!$isVotingOpen)
            <div class="fade-in d-flex flex-column align-items-center justify-content-center" style="min-height: 60vh;">
                <div class="locked-state-card glass-card text-center shadow-sm w-100" style="max-width: 500px;">
                    <div class="mb-4">
                        <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle mb-3 shadow-sm"
                            style="width: 120px; height: 120px;">
                            <i class="bi {{ $isMaintenance ? 'bi-tools' : 'bi-lock-fill' }} text-muted"
                                style="font-size: 4rem; line-height: 1;"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold text-dark">{{ $isMaintenance ? 'Under Maintenance' : 'Voting is Locked' }}</h3>
                    <p class="text-secondary mb-4">
                        {{ $isMaintenance
                            ? 'The system is currently undergoing emergency maintenance. We will be back shortly.'
                            : 'The voting portal is currently locked. Please contact your administrator for more information.' }}
                    </p>
                    <div class="d-grid px-4">
                        <a href="/students/dashboard" wire:navigate class="btn btn-secondary py-2">
                            <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        @elseif ($this->hasVoted)
            <div class="fade-in d-flex flex-column align-items-center justify-content-center" style="min-height: 65vh;">
                <div class="glass-card p-5 text-center shadow-sm border-0 bg-white"
                    style="max-width: 550px; border-radius: 25px;">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h2 class="fw-bold text-dark mb-2">Vote Submitted!</h2>
                    <p class="text-secondary mb-4">Hi <strong>{{ auth()->user()->name }}</strong>, your vote has been
                        securely recorded.</p>
                    <div class="receipt-box p-3 mb-4 text-start bg-light rounded-3 border">
                        <div class="d-flex justify-content-between mb-2">
                            <small class="text-secondary">Reference:</small>
                            <span class="text-dark small fw-bold">{{ auth()->user()->student->vote_reference }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-secondary">Date:</small>
                            <span
                                class="text-dark small fw-bold">{{ auth()->user()->student->voted_at?->format('M d, Y - h:i A') }}</span>
                        </div>
                    </div>
                    <a href="/students/dashboard" wire:navigate class="btn btn-glow w-100 py-3">Return to Dashboard</a>
                </div>
            </div>
        @else
            <div class="d-flex align-items-center justify-content-center mb-4 px-md-5 pt-4">
                @foreach ([1 => 'Select', 2 => 'Review', 3 => 'Confirm'] as $num => $label)
                    <div
                        class="step d-flex flex-column align-items-center {{ $currentStep == $num ? 'active' : ($currentStep > $num ? 'completed' : '') }}">
                        <div class="step-circle" style="width: 28px; height: 28px; font-size: 12px; line-height: 28px;">
                            {{ $num }}
                        </div>
                        <span class="step-label"
                            style="font-size: 10px; margin-top: 4px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                            {{ $label }}
                        </span>
                    </div>
                    @if ($num < 3)
                        <div class="step-line"
                            style="width: 40px; margin: 0 8px; height: 2px; position: relative; top: -10px;"></div>
                    @endif
                @endforeach
            </div>

            @if ($currentStep == 1)
                <div class="fade-in px-2 py-4">
                    @foreach ($this->electionData as $position)
                        <div class="mb-6 text-center">
                            <h5
                                class="text-xl font-bold text-gray-800 tracking-tight flex items-center justify-center gap-2">
                                <span class="h-px w-8 bg-[#10b981]">
                                </span> {{ $position->name }} <span class="h-px w-8 bg-[#10b981]"></span>
                            </h5>
                            <p class="text-[11px] text-gray-500 mt-1 uppercase tracking-[0.1em]">Select one candidate
                            </p>
                        </div>

                        <div class="flex flex-wrap justify-center gap-6 mb-16">
                            @foreach ($position->candidates as $candidate)
                                @php
                                    $isSelected = ($selections[$position->id] ?? null) == $candidate->id;
                                @endphp

                                <div wire:click="$set('selections.{{ $position->id }}', {{ $candidate->id }})"
                                    class="relative bg-white rounded-[2rem] shadow-sm border transition-all duration-300 cursor-pointer overflow-hidden group w-full max-w-[240px]
                                    {{ $isSelected ? 'border-[#10b981] ring-4 ring-emerald-50 shadow-xl scale-[1.05]' : 'hover:shadow-lg hover:border-gray-300 hover:-translate-y-2' }}">

                                    <div class="flex justify-end p-4 pb-0">
                                        @if ($isSelected)
                                            <i class="bi bi-check-circle-fill text-[#10b981] text-xl"></i>
                                        @else
                                            <i
                                                class="bi bi-circle text-gray-200 text-xl group-hover:text-emerald-200 transition-colors"></i>
                                        @endif
                                    </div>

                                    <div class="px-5 pb-6 text-center">
                                        <div class="relative inline-block mb-4">
                                            <div
                                                class="w-20 h-20 rounded-full overflow-hidden border-2 border-white shadow-md mx-auto ring-1 ring-gray-100">
                                                @if ($candidate->photo)
                                                    <img src="{{ asset('storage/' . $candidate->photo) }}"
                                                        class="w-full h-full object-cover">
                                                @elseif ($candidate->student?->photo)
                                                    <img src="{{ asset('storage/' . $candidate->student->photo) }}"
                                                        class="w-full h-full object-cover">
                                                @else
                                                    <div class="w-full h-full flex items-center justify-center text-white text-2xl font-bold"
                                                        style="background: {{ $this->getAvatarColor($candidate->id) }}">
                                                        {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}
                                                    </div>
                                                @endif
                                            </div>
                                            @if ($isSelected)
                                                <div
                                                    class="absolute bottom-0 right-1 bg-[#10b981] text-white w-6 h-6 flex items-center justify-center rounded-full border-2 border-white shadow-sm">
                                                    <i class="bi bi-check-lg text-[10px]"></i>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="mb-3">
                                            <h6
                                                class="text-sm font-black leading-tight mb-1 {{ $isSelected ? 'text-emerald-700' : 'text-gray-900' }}">
                                                {{ $candidate->student->first_name }}
                                                {{ $candidate->student->last_name }}
                                            </h6>
                                            <p class="text-[9px] font-bold text-[#10b981] uppercase tracking-[0.15em]">
                                                {{ $candidate->party_name ?? 'No Party Name' }}
                                            </p>
                                        </div>

                                        <div class="mt-auto px-2">
                                            <div
                                                class="w-full py-2 rounded-full text-[10px] font-black uppercase tracking-widest transition-all
                                {{ $isSelected
                                    ? 'bg-[#10b981] text-white shadow-lg shadow-emerald-100'
                                    : 'bg-gray-100 text-gray-400 group-hover:bg-emerald-50 group-hover:text-emerald-600' }}">
                                                {{ $isSelected ? 'Selected' : 'Vote' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                    <div class="mt-12 flex justify-end pb-10">
                        <button wire:click="setStep(2)"
                            class="group relative inline-flex items-center justify-center px-6 py-2.5 font-bold text-white transition-all duration-200 bg-[#10b981] rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10b981] shadow-lg hover:bg-emerald-600 active:scale-95">
                            <span class="flex items-center gap-2 uppercase tracking-wider text-[11px]">
                                Review Selections
                                <i class="bi bi-arrow-right group-hover:translate-x-1 transition-transform text-xs"></i>
                            </span>
                        </button>
                    </div>
                </div>
            @elseif ($currentStep == 2)
                <div class="fade-in px-2 py-4">
                    <div class="mb-8 text-center">
                        <h5
                            class="text-xl font-bold text-gray-800 tracking-tight flex items-center justify-center gap-2">
                            <span class="h-px w-8 bg-[#10b981]"></span>
                            Review Your Selections
                            <span class="h-px w-8 bg-[#10b981]"></span>
                        </h5>
                        <p class="text-[11px] text-gray-500 mt-1 uppercase tracking-[0.1em]">Please double check your
                            votes before confirming</p>
                    </div>

                    <div class="flex flex-wrap justify-center gap-6 mb-12">
                        @foreach ($this->electionData as $position)
                            @php
                                $candidate = $position->candidates->firstWhere(
                                    'id',
                                    $selections[$position->id] ?? null,
                                );
                            @endphp

                            @if ($candidate)
                                <div
                                    class="relative bg-white rounded-[2rem] shadow-md border border-emerald-100 overflow-hidden w-full max-w-[240px] p-6 text-center transition-all">
                                    <div class="absolute top-0 left-0 right-0 bg-emerald-50 py-1.5">
                                        <span class="text-[9px] font-black text-emerald-700 uppercase tracking-widest">
                                            {{ $position->name }}
                                        </span>
                                    </div>

                                    <div class="mt-4">
                                        <div
                                            class="w-16 h-16 rounded-full overflow-hidden border-2 border-white shadow-md mx-auto ring-1 ring-emerald-100 mb-3">
                                            @if ($candidate->photo)
                                                <img src="{{ asset('storage/' . $candidate->photo) }}"
                                                    class="w-full h-full object-cover">
                                            @elseif ($candidate->student?->photo)
                                                <img src="{{ asset('storage/' . $candidate->student->photo) }}"
                                                    class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-white text-xl font-bold"
                                                    style="background: {{ $this->getAvatarColor($candidate->id) }}">
                                                    {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>

                                        <h6 class="text-sm font-black text-gray-900 leading-tight mb-1">
                                            {{ $candidate->student->first_name }} {{ $candidate->student->last_name }}
                                        </h6>
                                        <p class="text-[9px] font-bold text-[#10b981] uppercase tracking-[0.15em] mb-3">
                                            {{ $candidate->party_name ?? 'Independent' }}
                                        </p>

                                        <div
                                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500 text-white text-[9px] font-black uppercase tracking-tighter shadow-sm">
                                            <i class="bi bi-check2-all"></i> Selected
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between mt-8 border-t pt-8">
                        <button wire:click="setStep(1)"
                            class="group relative inline-flex items-center justify-center px-6 py-2.5 font-bold text-emerald-600 transition-all duration-200 bg-emerald-50 rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 shadow-sm hover:bg-emerald-100 active:scale-95">
                            <span class="flex items-center gap-2 uppercase tracking-wider text-[11px]">
                                <i
                                    class="bi bi-arrow-left group-hover:-translate-x-1 transition-transform text-xs"></i>
                                Change Votes
                            </span>
                        </button>

                        <button wire:click="setStep(3)"
                            class="group relative inline-flex items-center justify-center px-6 py-2.5 font-bold text-white transition-all duration-200 bg-[#10b981] rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10b981] shadow-lg hover:bg-emerald-600 active:scale-95">
                            <span class="flex items-center gap-2 uppercase tracking-wider text-[11px]">
                                Confirm & Submit
                                <i class="bi bi-shield-check group-hover:scale-110 transition-transform text-xs"></i>
                            </span>
                        </button>
                    </div>
                </div>
            @elseif ($currentStep == 3)
                <div class="fade-in px-2 py-4 flex justify-center">
                    <div
                        class="relative bg-white rounded-[2rem] shadow-xl border border-emerald-100 overflow-hidden w-full max-w-[500px] p-10 text-center">

                        <div
                            class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-emerald-400 to-emerald-600">
                        </div>

                        <div class="mb-6">
                            <div
                                class="w-20 h-20 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="bi bi-shield-lock-fill text-[#10b981]" style="font-size: 2.5rem;"></i>
                            </div>
                            <h3 class="text-2xl font-black text-gray-800 tracking-tight mb-2">Final Confirmation</h3>
                            <p class="text-sm text-gray-500 leading-relaxed">
                                Are you sure about your selections? <br>
                                <span class="text-emerald-600 font-bold">Once you submit, you cannot change your
                                    vote.</span>
                            </p>
                        </div>

                        <div class="flex items-center justify-center gap-4 mt-8">

                            <button wire:click="setStep(2)"
                                class="group relative inline-flex items-center justify-center px-6 py-2.5 font-bold text-emerald-600 transition-all duration-200 bg-emerald-50 rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 shadow-sm hover:bg-emerald-100 active:scale-95">
                                <span class="flex items-center gap-2 uppercase tracking-wider text-[11px]">
                                    <i
                                        class="bi bi-arrow-left group-hover:-translate-x-1 transition-transform text-xs"></i>
                                    Review Again
                                </span>
                            </button>

                            <button wire:click="submitVote" wire:loading.attr="disabled"
                                class="group relative inline-flex items-center justify-center px-6 py-2.5 font-bold text-white transition-all duration-200 bg-[#10b981] rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#10b981] shadow-lg hover:bg-emerald-600 active:scale-95 disabled:opacity-75 disabled:cursor-not-allowed">

                                <span wire:loading.remove
                                    class="flex items-center gap-2 uppercase tracking-wider text-[11px]">
                                    Cast Official Vote
                                    <i
                                        class="bi bi-send-fill group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform text-xs"></i>
                                </span>

                                <span wire:loading
                                    class="flex items-center gap-2 uppercase tracking-wider text-[11px]">
                                    <i class="bi bi-arrow-repeat animate-spin"></i>
                                    Recording...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </main>
</div>

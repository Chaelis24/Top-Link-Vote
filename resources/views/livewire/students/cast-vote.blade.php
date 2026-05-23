<?php

use App\Mail\VoteConfirmed;
use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Computed};
use Illuminate\Support\Facades\{Auth, Mail, DB, Session, Log};
use App\Models\{Position, Candidate, Vote, ElectionCycle, ActivityLog, Setting};

new #[Layout('layouts.app')] #[Title('Digital Ballot')] class extends Component {
    // 1. STATE PROPERTIES
    public $profile_photo_path = '';
    public int $currentStep = 1;
    public array $selections = [];
    public $student;

    // 2. LIFECYCLE HOOKS
    public function mount()
    {
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

    // 3. COMPUTED PROPERTIES
    #[Computed]
    public function hasVoted()
    {
        return $this->student && $this->student->has_voted;
    }

    #[Computed]
    public function activeCycle()
    {
        return ElectionCycle::where('status', 'active')->first();
    }

    #[Computed]
    public function isVotingOpen()
    {
        $setting = Setting::where('key', 'allowVoting')->first();
        $activeCycle = $this->activeCycle;

        if (!$setting || !(bool) $setting->value) {
            return false;
        }

        if (!$activeCycle || now()->gt($activeCycle->voting_end)) {
            return false;
        }

        return true;
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
            ->whereHas('candidates', function ($query) use ($voterCourse) {
                $query->whereIn('status', ['approved', 'active'])->whereHas('student', function ($q) use ($voterCourse) {
                    $q->where('course', $voterCourse);
                });
            })
            ->with([
                'candidates' => function ($query) use ($voterCourse) {
                    $query->whereIn('status', ['approved', 'active'])->with('student');
                },
            ])
            ->orderBy('priority', 'asc')
            ->get();
    }

    // 4. ACTION METHODS (User Interactions)
    public function setStep($step)
    {
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
        if (!$this->isVotingOpen || $this->hasVoted) {
            $this->dispatch('swal', [
                'title' => 'Access Denied',
                'text' => 'Voting is either closed or you have already submitted your ballot.',
                'icon' => 'error',
            ]);
            return;
        }

        if (empty($this->selections)) {
            $this->dispatch('swal', [
                'title' => 'Empty Ballot',
                'text' => 'Please select at least one candidate before submitting.',
                'icon' => 'warning',
            ]);
            return;
        }

        $user = auth()->user();

        $throttleKey = 'submit-vote:' . $user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $this->dispatch('swal', [
                'title' => 'Please Wait',
                'text' => 'Your vote is currently being processed. Please wait a few seconds.',
                'icon' => 'warning',
            ]);
            return;
        }

        RateLimiter::hit($throttleKey, 10);

        $student = $user->student;
        $cycle = $this->activeCycle;

        if (!$cycle) {
            $this->dispatch('swal', [
                'title' => 'No Cycle',
                'text' => 'No active election cycle found',
                'icon' => 'warning',
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($student, $cycle, $user) {
                $lockedStudent = $user->student()->lockForUpdate()->first();
                if ($lockedStudent->has_voted) {
                    throw new \Exception('Student has already voted.');
                }
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

                $lockedStudent->update([
                    'has_voted' => true,
                    'voted_at' => now(),
                    'vote_reference' => $referenceNumber,
                ]);

                \App\Jobs\LogActivity::dispatch([
                    'user_id' => $user->id,
                    'student_id' => $student->id,
                    'action' => 'Voted',
                    'description' => "$referenceNumber",
                    'properties' => json_encode([
                        'selections' => $this->selections,
                        'reference' => $referenceNumber,
                    ]),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ])->onQueue('logs');

                try {
                    Mail::to($user->email)->send(new VoteConfirmed($student, $cycle, $referenceNumber));
                } catch (\Exception $e) {
                    Log::error('Mail Error: ' . $e->getMessage());
                }
            });

            RateLimiter::clear($throttleKey);

            event(new \App\Events\VoteUpdated((string) $student->course));
            $this->student = $student->fresh();
            unset($this->hasVoted);

            $this->dispatch('swal', [
                'title' => 'Success!',
                'text' => 'Your vote has been cast.',
                'icon' => 'success',
                'timer' => 4000,
            ]);
        } catch (\Exception $e) {
            Log::error('Vote Error: ' . $e->getMessage());
            $this->dispatch('swal', [
                'title' => 'Error!',
                'text' => 'Something went wrong while casting your vote.',
                'icon' => 'error',
            ]);
        }
    }

    // 5. HELPER / UTILITY METHODS
    public function checkMaintenance()
    {
        $isMaintenance = Setting::where('key', 'maintenanceMode')->value('value');

        if ($isMaintenance == '1' || $isMaintenance === true) {
            $this->dispatch('swal-maintenance', [
                'icon' => 'warning',
                'title' => 'System Maintenance',
                'text' => 'The system is undergoing maintenance. You will be logged out.',
            ]);
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

<div wire:poll.10s="checkMaintenance">
    @include('layouts.partials.student-sidebar')
    <main class="main-content">
        <div class="topbar">
            <div>
                <h2 class="mb-0 text-dark">Digital <span style="color: #10b981">Ballot</span></h2>
                <p class="text-secondary mb-0 small">Secure Electronic Voting System</p>
            </div>
        </div>

        @if ($this->hasVoted)
            <div class="fade-in d-flex flex-column align-items-center justify-content-center" style="min-height: 65vh;">
                <div class="glass-card p-5 text-center shadow-sm border-0 bg-white"
                    style="max-width: 550px; border-radius: 25px;">
                    <div class="mb-4">
                        <i class="bi bi-check-circle-fill text-success" style="font-size: 5rem;"></i>
                    </div>
                    <h2 class="fw-bold text-dark mb-2">Your vote has been submitted!</h2>
                    <p class="text-secondary mb-2">Hi <strong
                            class="text-primary">{{ auth()->user()->student->first_name }}
                            {{ substr(auth()->user()->student->middle_name, 0, 1) }}.
                            {{ auth()->user()->student->last_name }} {{ auth()->user()->student->suffix }}</strong>,
                        your vote has been securely recorded.</p>
                    <div class="mt-2 mb-2">
                        <a href="https://www.facebook.com/share/18ghE6XNFp/" target="_blank"
                            class="btn btn-light btn-sm d-flex align-items-center justify-content-between border shadow-sm px-3 py-2 rounded-3"
                            style="background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(5px);">
                            <div class="d-flex align-items-center gap-2">
                                <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center"
                                    style="width: 24px; height: 24px;">
                                    <img src="{{ asset('images/csc-logo.png') }}" class="w-100 h-100">
                                </div>
                                <span class="text-dark fw-bold" style="font-size: 13px;">Official Facebook Page of
                                    CSC</span>
                            </div>
                            <i class="bi bi-chevron-right text-primary small"></i>
                        </a>
                    </div>
                    <div class="receipt-box p-3 mb-4 text-start bg-light rounded-3 border">
                        <div class="d-flex justify-content-between">
                            <small class="text-secondary">Reference:</small>
                            <span class="text-dark small fw-bold">{{ auth()->user()->student->vote_reference }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-secondary">Student ID:</small>
                            <span class="text-dark small fw-bold">{{ auth()->user()->student->student_id }}</span>
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
        @elseif (!$this->isVotingOpen)
            <div class="fade-in d-flex flex-column align-items-center justify-content-center" style="min-height: 65vh;">
                <div class="glass-card p-5 text-center shadow-sm border-0 bg-white"
                    style="max-width: 550px; border-radius: 25px;">
                    <div class="mb-4">
                        <i class="bi bi-calendar2-week text-muted" style="font-size: 3.5rem;"></i>
                    </div>
                    <h3 class="fw-bold text-dark mb-2">Voting is Currently Inactive</h3>
                    <p class="text-secondary mb-4 px-3">
                        There is no active election cycle at this time. Please check the Commission on Elections
                        announcement for the official timeline and schedule.
                    </p>
                    <a href="/students/dashboard" wire:navigate class="btn btn-secondary btn-sm w-75 py-2 fw-bold">
                        Return to Dashboard
                    </a>
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
                <div class="fade-in px-2 py-2" x-transition>
                    @forelse ($this->electionData as $position)
                        <div class="mb-6 text-center">
                            <h5
                                class="text-lg md:text-xl font-bold text-gray-800 tracking-tight flex items-center justify-center gap-2">
                                <span class="h-px w-6 md:w-8 bg-[#10b981]"></span>
                                {{ $position->name }}
                                <span class="h-px w-6 md:w-8 bg-[#10b981]"></span>
                            </h5>
                            @if ($position->candidates->isNotEmpty())
                                <p class="text-[10px] md:text-[11px] text-gray-500 mt-1 uppercase tracking-[0.1em]">
                                    Select one candidate</p>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 md:flex md:flex-wrap justify-center gap-3 md:gap-6 mb-8">
                            @forelse ($position->candidates as $candidate)
                                @php
                                    $isSelected = ($selections[$position->id] ?? null) == $candidate->id;
                                @endphp

                                <div wire:click="$set('selections.{{ $position->id }}', {{ $candidate->id }})"
                                    class="relative bg-white rounded-[1.2rem] md:rounded-[2rem] shadow-sm border transition-all duration-300 cursor-pointer overflow-hidden group
                        {{ $isSelected ? 'border-[#10b981] ring-2 md:ring-4 ring-emerald-50 shadow-xl scale-[1.02] md:scale-[1.05]' : 'hover:shadow-lg hover:border-gray-300' }} w-full md:max-w-[240px]">

                                    <div class="flex justify-end p-3 md:p-4 pb-0">
                                        @if ($isSelected)
                                            <i class="bi bi-check-circle-fill text-[#10b981] text-lg md:text-xl"></i>
                                        @else
                                            <i
                                                class="bi bi-circle text-gray-200 text-lg md:text-xl group-hover:text-emerald-200 transition-colors"></i>
                                        @endif
                                    </div>

                                    <div class="px-5 pb-6 text-center">
                                        <div class="relative inline-block mb-3 md:mb-4">
                                            <div
                                                class="w-16 h-16 md:w-20 md:h-20 rounded-full overflow-hidden border-2 border-white shadow-md mx-auto ring-1 ring-gray-100">
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
                                                class="text-xs md:text-sm font-black leading-tight mb-1 {{ $isSelected ? 'text-emerald-700' : 'text-gray-900' }}">
                                                {{ $candidate->student->first_name }}<br class="md:hidden">
                                                {{ $candidate->student->last_name }}
                                            </h6>
                                            <p
                                                class="text-[8px] md:text-[9px] font-bold text-[#10b981] uppercase tracking-[0.1em] md:tracking-[0.15em] truncate">
                                                {{ $candidate->party_name ?? 'No Party' }}
                                            </p>
                                        </div>

                                        <div class="mt-auto px-1 md:px-2">
                                            <div
                                                class="w-full py-1.5 md:py-2 rounded-full text-[9px] md:text-[10px] font-black uppercase tracking-widest transition-all {{ $isSelected ? 'bg-[#10b981] text-white' : 'bg-gray-100 text-gray-400' }}">
                                                {{ $isSelected ? 'Selected' : 'Vote' }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <div
                                    class="col-span-2 w-full py-10 flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-[2rem] bg-gray-50/50">
                                    <i class="bi bi-people text-gray-300 text-4xl mb-2"></i>
                                    <p class="text-gray-400 font-bold text-xs uppercase tracking-widest">No candidates
                                        available</p>
                                </div>
                            @endforelse
                        </div>
                    @empty
                        <div class="text-center py-20">
                            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-50 mb-4">
                                <i class="bi bi-folder-x text-gray-300 text-4xl"></i>
                            </div>
                            <h3 class="text-gray-800 font-black text-lg">No Election Data Found</h3>
                            <p class="text-gray-500 text-sm">There are no positions available for your department at
                                this time.</p>
                        </div>
                    @endforelse

                    @if ($this->electionData->isNotEmpty())
                        <div class="mt-12 flex justify-end pb-10 mb-2 md:mb-12">
                            <button wire:click="setStep(2)" wire:loading.attr="disabled" wire:target="setStep(2)"
                                class="group relative inline-flex items-center justify-center px-6 py-2.5 font-bold text-white transition-all duration-200 bg-[#10b981] rounded-full shadow-lg hover:bg-emerald-600 active:scale-95 disabled:opacity-75">
                                <span wire:loading.remove wire:target="setStep(2)"
                                    class="flex items-center gap-2 uppercase tracking-wider text-[11px]">
                                    Review Selections
                                    <i
                                        class="bi bi-arrow-right group-hover:translate-x-1 transition-transform text-xs"></i>
                                </span>
                                <span wire:loading wire:target="setStep(2)"
                                    class="flex items-center gap-2 uppercase tracking-wider text-[11px]">
                                    <span class="spinner-border spinner-border-sm"></span>
                                    Preparing Review...
                                </span>
                            </button>
                        </div>
                    @endif
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
                    </div>

                    <div class="grid grid-cols-2 md:flex md:flex-wrap justify-center gap-3 md:gap-6 mb-12">
                        @forelse ($this->electionData as $position)
                            @php
                                $candidate = $position->candidates->firstWhere(
                                    'id',
                                    $selections[$position->id] ?? null,
                                );
                            @endphp

                            @if ($candidate)
                                <div
                                    class="relative bg-white rounded-[1.5rem] md:rounded-[2rem] shadow-md border border-emerald-100 overflow-hidden w-full md:max-w-[240px] p-4 md:p-6 text-center transition-all">
                                    <div class="absolute top-0 left-0 right-0 bg-emerald-50 py-1.5 px-1">
                                        <span
                                            class="text-[8px] md:text-[9px] font-black text-emerald-700 uppercase tracking-widest truncate block">
                                            {{ $position->name }}
                                        </span>
                                    </div>

                                    <div class="mt-4">
                                        <div
                                            class="w-12 h-12 md:w-16 md:h-16 rounded-full overflow-hidden border-2 border-white shadow-md mx-auto ring-1 ring-emerald-100 mb-3">
                                            @if ($candidate->photo)
                                                <img src="{{ asset('storage/' . $candidate->photo) }}"
                                                    class="w-full h-full object-cover">
                                            @elseif ($candidate->student?->photo)
                                                <img src="{{ asset('storage/' . $candidate->student->photo) }}"
                                                    class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full flex items-center justify-center text-white text-lg md:text-xl font-bold"
                                                    style="background: {{ $this->getAvatarColor($candidate->id) }}">
                                                    {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>

                                        <h6 class="text-[11px] md:text-sm font-black text-gray-900 leading-tight mb-1">
                                            {{ $candidate->student->first_name }}<br class="md:hidden">
                                            {{ $candidate->student->last_name }}
                                        </h6>
                                        <p
                                            class="text-[8px] md:text-[9px] font-bold text-[#10b981] uppercase tracking-[0.1em] mb-3 truncate">
                                            {{ $candidate->party_name ?? 'Independent' }}
                                        </p>

                                        <div
                                            class="inline-flex items-center gap-1 px-2 md:px-3 py-1 rounded-full bg-emerald-500 text-white text-[8px] md:text-[9px] font-black uppercase tracking-tighter shadow-sm">
                                            <i class="bi bi-check2-all"></i> Selected
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div
                                    class="relative bg-gray-50 rounded-[1.5rem] md:rounded-[2rem] border border-dashed border-gray-200 overflow-hidden w-full md:max-w-[240px] p-4 md:p-6 text-center">
                                    <div class="absolute top-0 left-0 right-0 bg-gray-100 py-1.5 px-1">
                                        <span
                                            class="text-[8px] md:text-[9px] font-black text-gray-400 uppercase tracking-widest truncate block">
                                            {{ $position->name }}
                                        </span>
                                    </div>
                                    <div class="mt-4 py-4">
                                        <i class="bi bi-dash-circle text-gray-300 text-2xl mb-2"></i>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase">Abstain / No Selection
                                        </p>
                                    </div>
                                </div>
                            @endif
                        @empty
                        @endforelse
                    </div>

                    <div class="flex items-center justify-between mt-8 border-t pt-8 pb-10">
                        <button wire:click="setStep(1)"
                            class="group relative inline-flex items-center justify-center px-4 md:px-6 py-2.5 font-bold text-emerald-600 transition-all duration-200 bg-emerald-50 rounded-full focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-sm hover:bg-emerald-100 active:scale-95">
                            <span
                                class="flex items-center gap-1.5 md:gap-2 uppercase tracking-wider text-[9px] md:text-[11px]">
                                <i
                                    class="bi bi-arrow-left group-hover:-translate-x-1 transition-transform text-xs"></i>
                                <span class="hidden xs:inline">Change Votes</span>
                                <span class="xs:hidden">Back</span>
                            </span>
                        </button>

                        <button wire:click="setStep(3)" wire:loading.attr="disabled" wire:target="setStep(3)"
                            class="group relative inline-flex items-center justify-center px-4 md:px-6 py-2.5 font-bold text-white transition-all duration-200 bg-[#10b981] rounded-full shadow-lg hover:bg-emerald-600 active:scale-95 disabled:opacity-75">
                            <span wire:loading.remove wire:target="setStep(3)"
                                class="flex items-center gap-1.5 md:gap-2 uppercase tracking-wider text-[9px] md:text-[11px]">
                                Confirm <span class="hidden xs:inline">& Submit</span>
                                <i class="bi bi-shield-check group-hover:scale-110 transition-transform text-xs"></i>
                            </span>
                            <span wire:loading wire:target="setStep(3)"
                                class="flex items-center gap-1.5 md:gap-2 uppercase tracking-wider text-[9px] md:text-[11px]">
                                <span class="spinner-border spinner-border-sm"></span>
                                Processing...
                            </span>
                        </button>
                    </div>
                </div>
            @elseif ($currentStep == 3)
                <div class="fade-in px-2 py-4 flex justify-center">
                    <div
                        class="relative bg-white rounded-[1.5rem] md:rounded-[2rem] shadow-xl border border-emerald-100 overflow-hidden w-full max-w-[500px] p-6 md:p-10 text-center">
                        <div
                            class="absolute top-0 left-0 right-0 h-2 bg-gradient-to-r from-emerald-400 to-emerald-600">
                        </div>
                        <div class="mb-6">
                            <div
                                class="w-16 h-16 md:w-20 md:h-20 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="bi bi-shield-lock-fill text-[#10b981] text-[1.8rem] md:text-[2.5rem]"></i>
                            </div>
                            <h3 class="text-xl md:text-2xl font-black text-gray-800 tracking-tight mb-2">Final
                                Confirmation</h3>
                            <p class="text-[12px] md:text-sm text-gray-500 leading-relaxed px-2">
                                Are you sure about your selections? <br class="hidden md:block">
                                <span class="text-emerald-600 font-bold block md:inline">Once you submit, you cannot
                                    change your vote.</span>
                            </p>
                        </div>
                        <div class="flex flex-col xs:flex-row items-center justify-center gap-3 md:gap-4 mt-8">
                            <button wire:click="setStep(2)"
                                class="w-full xs:w-auto group relative inline-flex items-center justify-center px-6 py-3 md:py-2.5 font-bold text-emerald-600 transition-all duration-200 bg-emerald-50 rounded-full shadow-sm hover:bg-emerald-100 active:scale-95">
                                <span
                                    class="flex items-center gap-2 uppercase tracking-wider text-[10px] md:text-[11px]">
                                    <i
                                        class="bi bi-arrow-left group-hover:-translate-x-1 transition-transform text-xs"></i>
                                    Review Again
                                </span>
                            </button>
                            <div x-data="{ isOffline: !navigator.onLine }" x-init="window.addEventListener('online', () => isOffline = false);
                            window.addEventListener('offline', () => isOffline = true)">

                                <button wire:click="submitVote" wire:loading.attr="disabled" :disabled="isOffline"
                                    class="w-full xs:w-auto group relative inline-flex items-center justify-center px-6 py-3 md:py-2.5 font-bold text-white transition-all duration-200 rounded-full shadow-lg active:scale-95 disabled:opacity-75"
                                    :class="isOffline ? 'bg-secondary cursor-not-allowed' : 'bg-[#10b981] hover:bg-emerald-600'">

                                    <span x-show="!isOffline" wire:loading.remove wire:target="submitVote"
                                        class="flex items-center gap-2 uppercase tracking-wider text-[10px] md:text-[11px]">
                                        Cast Official Vote
                                        <i
                                            class="bi bi-send-fill group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform text-xs"></i>
                                    </span>

                                    <span x-show="isOffline" x-cloak
                                        class="flex items-center gap-2 uppercase tracking-wider text-[10px] md:text-[11px]">
                                        <i class="bi bi-wifi-off text-xs"></i>
                                        Offline: Check Connection
                                    </span>

                                    <span wire:loading wire:target="submitVote"
                                        class="flex items-center gap-2 uppercase tracking-wider text-[10px] md:text-[11px]">
                                        <span class="spinner-border spinner-border-sm" role="status"
                                            aria-hidden="true"></span>
                                        Recording...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </main>
</div>

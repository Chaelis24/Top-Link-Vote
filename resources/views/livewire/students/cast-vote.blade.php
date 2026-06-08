<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Computed};
use App\Traits\{ChecksMaintenance, AuthenticatesLogout};
use Illuminate\Support\Facades\Auth;
use App\Services\Student\CastVoteService;
use App\Models\Vote;

new #[Layout('layouts.app')] #[Title('Digital Ballot')] class extends Component {
    use ChecksMaintenance, AuthenticatesLogout;

    public $profile_photo_path = '';
    public int $currentStep = 1;
    public int $currentPositionIndex = 0;
    public array $selections = [];
    public $student;
    public bool $showNames = false;

    private CastVoteService $castVoteService;

    public function boot(CastVoteService $castVoteService)
    {
        $this->castVoteService = $castVoteService;
    }

    public function mount()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $data = $this->castVoteService->getStudentData($user);
        $this->student = $data['student'];
        $this->profile_photo_path = $data['profile_photo_path'];

        if ($this->isVotingOpen) {
            if ($this->hasVoted) {
                return;
            }

            foreach ($this->electionData as $position) {
                $this->selections[$position->id] = null;
            }

            $existingVotes = Vote::where('student_id', $this->student->id)->where('election_cycle_id', $this->activeCycle->id)->get();

            if ($existingVotes->isNotEmpty()) {
                foreach ($existingVotes as $vote) {
                    $this->selections[$vote->position_id] = $vote->candidate_id;
                }

                $positionIds = $this->electionData->pluck('id')->toArray();
                $votedIds = $existingVotes->pluck('position_id')->toArray();
                $remaining = array_values(array_diff($positionIds, $votedIds));

                if (empty($remaining)) {
                    $this->currentStep = 2;
                } else {
                    $firstRemainingId = $remaining[0];
                    foreach ($this->electionData as $index => $position) {
                        if ($position->id === $firstRemainingId) {
                            $this->currentPositionIndex = $index;
                            break;
                        }
                    }
                }
            }
        }
    }

    #[Computed]
    public function hasVoted()
    {
        return $this->castVoteService->hasStudentVoted($this->student);
    }

    #[Computed]
    public function activeCycle()
    {
        return $this->castVoteService->getActiveCycle();
    }

    #[Computed]
    public function isVotingOpen()
    {
        return $this->castVoteService->isVotingOpen($this->activeCycle);
    }

    #[Computed]
    public function electionData()
    {
        $courseId = $this->castVoteService->getVoterCourseId();
        return $this->castVoteService->getElectionData($this->activeCycle, $this->isVotingOpen, $courseId);
    }

    #[Computed]
    public function currentPosition()
    {
        return $this->electionData[$this->currentPositionIndex] ?? null;
    }

    #[Computed]
    public function isLastPosition()
    {
        return $this->currentPositionIndex >= count($this->electionData) - 1;
    }

    public function selectCandidate($positionId, $candidateId)
    {
        if ($this->currentStep !== 1) {
            return;
        }
        $this->selections[$positionId] = $candidateId;
    }

    public function nextPosition()
    {
        if ($this->currentStep !== 1) {
            return;
        }

        $position = $this->currentPosition;
        if (!$position) {
            return;
        }

        if ($position->candidates->isNotEmpty()) {
            $candidateId = $this->selections[$position->id] ?? null;
            if (!$candidateId) {
                $this->dispatch('swal', [
                    'title' => 'No Selection',
                    'text' => 'Please select a candidate before proceeding.',
                    'icon' => 'warning',
                ]);
                return;
            }
        }

        if ($this->isLastPosition) {
            $this->currentStep = 2;
        } else {
            $this->currentPositionIndex++;

            $this->currentPosition = $this->electionData[$this->currentPositionIndex] ?? null;
        }
    }

    public function setStep($step)
    {
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

        if (empty(array_filter($this->selections))) {
            $this->dispatch('swal', [
                'title' => 'Empty Ballot',
                'text' => 'Please select at least one candidate before submitting.',
                'icon' => 'warning',
            ]);
            return;
        }

        $user = auth()->user();
        $cycle = $this->activeCycle;

        $result = $this->castVoteService->submitVote($user, $this->selections, $cycle);

        if (isset($result['error'])) {
            $this->dispatch('swal', [
                'title' => 'Error!',
                'text' => $result['error'],
                'icon' => 'error',
            ]);
            return;
        }

        $this->student = $result['student'];
        auth()->user()->setRelation('student', $this->student);

        $this->dispatch('swal', [
            'title' => 'Success!',
            'text' => 'Your vote has been cast.',
            'icon' => 'success',
            'timer' => 4000,
        ]);
    }

    public function getAvatarColor($id)
    {
        return $this->castVoteService->getAvatarColor($id);
    }
}; ?>

<div>
    <div class="d-lg-none d-flex align-items-center justify-content-start p-2 px-4 bg-white shadow-sm gap-2 border-bottom">
        <img src="{{ asset('images/logo.png') }}" alt="Logo" style="height: 45px; width: 45px; object-fit: contain;">

        <h4 class="mb-0 text-primary" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
            Top Link Global College, Inc.
        </h4>
    </div>

    @include('layouts.partials.student-sidebar')
    <main class="main-content">
        <div class="topbar">
            <div>
                <h2 class="mb-0 text-dark">Digital <span style="color: #10b981">Ballot</span></h2>
                <p class="text-secondary mb-0 small">Secure Electronic Voting System</p>
            </div>
        </div>

        @php
            $suffix = auth()->user()->student->suffix;
            $formattedSuffix = in_array($suffix, ['Jr', 'Sr']) ? $suffix . '.' : $suffix;
        @endphp

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
                            {{ auth()->user()->student->middle_name ? substr(auth()->user()->student->middle_name, 0, 1) . '.' : '' }}
                            {{ auth()->user()->student->last_name }} {{ $formattedSuffix ?? '' }}</strong>,
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
                            <span
                                class="text-dark small fw-bold">{{ auth()->user()->student->vote_reference ?? 'N/A' }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-secondary">Student ID:</small>
                            <span class="text-dark small fw-bold">{{ auth()->user()->student->student_id }}</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-secondary">Date:</small>
                            <span
                                class="text-dark small fw-bold">{{ auth()->user()->student->voted_at?->format('M d, Y - h:i A') ?? 'N/A' }}</span>
                        </div>
                    </div>
                    <a href="/students/dashboard" wire:navigate class="btn btn-glow w-100 py-2">Return to Dashboard</a>
                </div>
            </div>
        @elseif (!$this->isVotingOpen)
            <div class="d-flex flex-column align-items-center justify-content-center" style="min-height: 65vh;">
                <div class="text-center" style="max-width: 550px; border-radius: 25px;">
                    <div class="mb-6">
                        <div class="inline-flex p-4 rounded-full bg-emerald-50">
                            <i class="bi bi-calendar2-week text-emerald-600 text-5xl"></i>
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-3">Voting is Currently Inactive</h3>
                    <p class="text-secondary mx-auto mb-4" style="max-width: 500px;">
                        There is no active election cycle at this time. Please monitor the official announcements from
                        the Commission on Elections for the upcoming schedule.
                    </p>
                </div>
            </div>
        @else
            <div class="d-flex align-items-center justify-content-center mb-0 px-md-5 pt-4 p-6 p-md-0">
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
                            style="width: 40px; margin: 0 8px; height: 2px; position: relative; top: -10px;">
                        </div>
                    @endif
                @endforeach
            </div>

            @if ($currentStep == 1)
                @php
                    $position = $this->currentPosition;
                    $totalPositions = count($this->electionData);
                    $positionNumber = $this->currentPositionIndex + 1;
                    $hasCandidates = $position && $position->candidates->isNotEmpty();
                    $hasSelection =
                        $position && isset($selections[$position->id]) && !is_null($selections[$position->id]);
                @endphp

                @if ($position)
                    <div class="fade-in px-2 py-2" wire:key="position-{{ $position->id }}">
                        <div class="mb-2 text-center">
                            <span
                                class="text-[10px] md:text-[11px] font-bold text-gray-400 uppercase tracking-[0.15em]">
                                Position {{ $positionNumber }} of {{ $totalPositions }}
                            </span>
                        </div>

                        <div class="mb-6 text-center">
                            <h5
                                class="text-sm md:text-base font-bold text-gray-800 tracking-tight flex items-center justify-center gap-2 uppercase">
                                <span class="h-px w-6 md:w-8 bg-[#10b981]"></span>
                                {{ $position->name }}
                                <span class="h-px w-6 md:w-8 bg-[#10b981]"></span>
                            </h5>
                            @if ($hasCandidates)
                                <p class="text-[10px] md:text-[11px] text-gray-500 mt-1 uppercase tracking-[0.1em]">
                                    Select one candidate</p>
                            @endif
                        </div>

                        @if ($hasCandidates)
                            <div class="grid grid-cols-2 md:flex md:flex-wrap justify-center gap-3 md:gap-6 mb-8">
                                @foreach ($position->candidates as $candidate)
                                    @php
                                        $isSelected = ($selections[$position->id] ?? null) == $candidate->id;
                                    @endphp

                                    <div wire:click="selectCandidate({{ $position->id }}, {{ $candidate->id }})"
                                        class="relative bg-white rounded-[1.2rem] md:rounded-[2rem] shadow-sm border transition-all duration-300 cursor-pointer overflow-hidden group
                                        {{ $isSelected ? 'border-[#10b981] ring-2 md:ring-4 ring-emerald-50 shadow-xl scale-[1.02] md:scale-[1.05]' : 'hover:shadow-lg hover:border-gray-300' }} w-full md:max-w-[240px]">

                                        <div class="flex justify-end p-3 md:p-4 pb-0">
                                            @if ($isSelected)
                                                <i
                                                    class="bi bi-check-circle-fill text-[#10b981] text-lg md:text-xl"></i>
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
                                                    class="text-[12px] md:text-[13px] font-black leading-tight mb-1 {{ $isSelected ? 'text-emerald-700' : 'text-gray-900' }}">
                                                    {{ $candidate->student->first_name }}<br class="md:hidden">
                                                    {{ $candidate->student->last_name }}
                                                </h6>
                                                <p
                                                    class="text-[8px] md:text-[9px] font-black text-[#10b981] uppercase tracking-[0.1em] md:tracking-[0.15em] truncate">
                                                    {{ $candidate->party_name ?? 'No Party Name' }}
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
                                @endforeach
                            </div>
                        @else
                            <div class="flex justify-center mb-8">
                                <div
                                    class="w-full max-w-md py-12 flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-[2rem] bg-gray-50/50">
                                    <i class="bi bi-people text-gray-300 text-4xl mb-2"></i>
                                    <p class="text-gray-400 font-bold text-xs uppercase tracking-widest">No candidates
                                        available</p>
                                </div>
                            </div>
                        @endif

                        <div class="mt-8 flex justify-end pb-10 mb-3 md:mb-4">
                            <button wire:click="nextPosition" wire:loading.attr="disabled" wire:target="nextPosition"
                                class="group relative inline-flex items-center justify-center px-4 md:px-6 py-1.5 md:py-2.5 font-bold text-white transition-all duration-200 bg-[#10b981] rounded-full shadow-lg hover:bg-emerald-600 active:scale-95 disabled:opacity-75 {{ !$hasSelection && $hasCandidates ? 'opacity-50 cursor-not-allowed' : '' }}"
                                {{ !$hasSelection && $hasCandidates ? 'disabled' : '' }}>

                                <span wire:loading.remove wire:target="nextPosition"
                                    class="flex items-center gap-1.5 md:gap-2 uppercase tracking-wider text-[9px] md:text-[11px]">
                                    {{ $this->isLastPosition ? 'Review Selections' : 'Next Position' }}
                                    <i
                                        class="bi bi-arrow-right group-hover:translate-x-1 transition-transform text-[10px] md:text-xs"></i>
                                </span>

                                <span wire:loading wire:target="nextPosition"
                                    class="flex items-center gap-1.5 md:gap-2 uppercase tracking-wider text-[9px] md:text-[11px]">
                                    <span class="spinner-border spinner-border-sm"></span>
                                    Saving...
                                </span>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="text-center py-20">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-50 mb-4">
                            <i class="bi bi-folder-x text-gray-300 text-4xl"></i>
                        </div>
                        <h3 class="text-gray-800 font-black text-lg">No Election Data Found</h3>
                        <p class="text-gray-500 text-sm">There are no positions available for your department at
                            this time.</p>
                    </div>
                @endif
            @elseif ($currentStep == 2)
                <div class="fade-in px-2 py-3">
                    <div class="mb-3 text-center">
                        <h5
                            class="text-xl font-bold text-gray-800 tracking-tight flex items-center justify-center gap-2">
                            <span class="h-px w-8 bg-[#10b981]"></span>
                            Review Your Selections
                            <span class="h-px w-8 bg-[#10b981]"></span>
                        </h5>
                    </div>

                    <div class="grid grid-cols-2 md:flex md:flex-wrap justify-center gap-3 md:gap-4 mb-12">
                        @forelse ($this->electionData as $position)
                            @php
                                $candidate = $position->candidates->firstWhere(
                                    'id',
                                    $selections[$position->id] ?? null,
                                );
                            @endphp

                            @if ($candidate)
                                <div
                                    class="relative bg-white rounded-[1.5rem] md:rounded-[rem] shadow-md border border-emerald-100 overflow-hidden w-full md:max-w-[240px] p-4 md:p-4 text-center transition-all">
                                    <div>
                                        <h6
                                            class="transition-all text-[11px] md:text-sm font-black text-gray-900 leading-tight mb-1 {{ $showNames ? '' : 'blur-[4px] select-none' }}">
                                            {{ $candidate->student->first_name }}<br class="md:hidden">
                                            {{ $candidate->student->last_name }}
                                        </h6>
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

                    <div class="mt-8 flex items-center justify-end gap-4 pb-10 mb-3 md:mb-4">
                        <button wire:click="$toggle('showNames')"
                            class="flex items-center gap-2 px-3 py-2 text-[10px] font-bold text-gray-500 hover:text-[#10b981] transition-colors uppercase tracking-widest"
                            title="{{ $showNames ? 'Hide Names' : 'Show Names' }}">
                            <i class="bi {{ $showNames ? 'bi-eye-slash' : 'bi-eye' }} text-lg"></i>
                            <span>{{ $showNames ? 'Hide' : 'Show' }}</span>
                        </button>

                        <button wire:click="setStep(3)" wire:loading.attr="disabled" wire:target="setStep(3)"
                            class="group relative inline-flex items-center justify-center px-4 md:px-6 py-1.5 md:py-2.5 font-bold text-white transition-all duration-200 bg-[#10b981] rounded-full shadow-lg hover:bg-emerald-600 active:scale-95 disabled:opacity-75">

                            <span wire:loading.remove wire:target="setStep(3)"
                                class="flex items-center gap-1.5 md:gap-2 uppercase tracking-wider text-[9px] md:text-[11px]">
                                Confirm
                                <i
                                    class="bi bi-shield-check group-hover:scale-110 transition-transform text-[10px] md:text-xs"></i>
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
                                    class="w-full xs:w-auto group relative inline-flex items-center justify-center px-6 py-3 md:py-2.5 font-bold text-white transition-all duration-200 rounded-full shadow-lg active:scale-95 disabled:opacity-75 min-h-[44px] md:min-h-[42px]"
                                    :class="isOffline ? 'bg-secondary cursor-not-allowed' : 'bg-[#10b981] hover:bg-emerald-600'">

                                    <span x-show="!isOffline" wire:loading.remove wire:target="submitVote"
                                        class="flex items-center gap-2 uppercase tracking-wider text-[10px] md:text-[11px] h-full">
                                        Cast Official Vote
                                        <i
                                            class="bi bi-send-fill group-hover:translate-x-1 group-hover:-translate-y-1 transition-transform text-xs"></i>
                                    </span>

                                    <span x-show="isOffline" x-cloak
                                        class="flex items-center gap-2 uppercase tracking-wider text-[10px] md:text-[11px] h-full">
                                        <i class="bi bi-wifi-off text-xs"></i>
                                        No Internet
                                    </span>

                                    <span wire:loading wire:target="submitVote"
                                        class="flex items-center gap-2 uppercase tracking-wider text-[10px] md:text-[11px] h-full">
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

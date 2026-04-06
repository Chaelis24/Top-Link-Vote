<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Illuminate\Support\Facades\{Auth, Session};
use Illuminate\Support\Str;
use App\Models\{Candidate, Position, ElectionCycle, Platform};

new #[Layout('layouts.app')] #[Title('Platforms')] class extends Component {
    #[Url]
    public string $selectedPosition = 'All Positions';

    public string $platform_title = '';
    public string $vision = '';
    public string $mission = '';
    public array $goals = [''];
    public array $action_plans = [''];
    public $profile_photo_path = '';

    public function mount()
    {
        $user = Auth::user()?->load('student');
        $this->student = $user?->student;

        if ($this->student) {
            $this->profile_photo_path = $this->student->photo ?? ($this->student->profile_photo_path ?? null);
        }

        if ($this->isEligibleToEdit) {
            $candidate = Auth::user()->candidate;
            $platform = Platform::where('candidate_id', $candidate->id)->latest()->first();

            if ($platform) {
                $this->platform_title = $platform->title ?? '';
                $this->vision = $platform->vision ?? ($candidate->slogan ?? '');
                $this->mission = $platform->mission ?? ($candidate->bio ?? '');
                $this->goals = is_array($platform->goals) && count($platform->goals) > 0 ? $platform->goals : [''];
                $this->action_plans = is_array($platform->action_plans) && count($platform->action_plans) > 0 ? $platform->action_plans : [''];
            } else {
                $this->platform_title = 'Official Campaign Platform';
                $this->vision = $candidate->slogan ?? '';
                $this->mission = $candidate->bio ?? '';
            }
        }
    }

    #[Computed]
    public function isEligibleToEdit()
    {
        $user = Auth::user();
        return $user && $user->hasRole('Student') && $user->hasRole('Candidate') && $user->candidate;
    }

    #[Computed]
    public function positionsList()
    {
        $voterCourse = Auth::user()->student->course ?? '';
        $activeCycle = ElectionCycle::where('status', 'active')->first();

        if (!$activeCycle) {
            return collect(['All Positions']);
        }

        return collect(['All Positions'])->concat(
            Position::where('election_cycle_id', $activeCycle->id)
                ->where(function ($query) use ($voterCourse) {
                    $query->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourse);
                })
                ->whereHas('candidates', function ($q) {
                    $q->whereIn('status', ['approved', 'active']);
                })
                ->pluck('name')
                ->unique(),
        );
    }

    #[Computed]
    public function filteredCandidates()
    {
        $voterCourse = Auth::user()->student->course ?? '';
        $activeCycle = ElectionCycle::where('status', 'active')->first();

        if (!$activeCycle) {
            return collect();
        }

        return Candidate::with(['student.user', 'position', 'platforms' => fn($q) => $q->where('status', 'approved')->latest()])
            ->where('election_cycle_id', $activeCycle->id)
            ->whereIn('status', ['approved', 'active'])
            ->whereHas('position', function ($query) use ($voterCourse) {
                $query->whereNull('student_department')->orWhere('student_department', '')->orWhere('student_department', $voterCourse);
            })
            ->when($this->selectedPosition !== 'All Positions', function ($query) {
                $query->whereHas('position', fn($q) => $q->where('name', $this->selectedPosition));
            })
            ->get();
    }

    public function addGoal()
    {
        $this->goals[] = '';
    }
    public function removeGoal($index)
    {
        unset($this->goals[$index]);
        $this->goals = array_values($this->goals);
    }
    public function addActionPlan()
    {
        $this->action_plans[] = '';
    }
    public function removeActionPlan($index)
    {
        unset($this->action_plans[$index]);
        $this->action_plans = array_values($this->action_plans);
    }

    public function updatePlatform()
    {
        if (!$this->isEligibleToEdit) {
            return $this->dispatch('swal', title: 'Unauthorized', text: 'Action not allowed.', icon: 'error');
        }

        $this->validate([
            'platform_title' => 'required|string|min:5|max:255',
            'vision' => 'required|string|min:5|max:255',
            'mission' => 'required|string|min:10',
            'goals' => 'required|array|min:1',
            'goals.*' => 'required|string|min:3',
            'action_plans' => 'required|array|min:1',
            'action_plans.*' => 'required|string|min:3',
        ]);

        $cleanGoals = array_values(array_filter($this->goals, fn($val) => !empty(trim($val))));
        $cleanActionPlans = array_values(array_filter($this->action_plans, fn($val) => !empty(trim($val))));

        Platform::create([
            'candidate_id' => Auth::user()->candidate->id,
            'title' => $this->platform_title,
            'vision' => $this->vision,
            'mission' => $this->mission,
            'goals' => $cleanGoals,
            'action_plans' => $cleanActionPlans,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->dispatch('swal', title: 'Submitted!', text: 'Waiting for admin review.', icon: 'success');
        $this->dispatch('close-modal');
    }

    public function selectPosition($pos)
    {
        $this->selectedPosition = $pos;
    }
    public function getAvatarColor($id)
    {
        $colors = ['#388E3C', '#1976D2', '#D32F2F', '#FBC02D', '#8E24AA', '#E64A19'];
        return $colors[$id % count($colors)];
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
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.student-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2>Candidate <span>Platforms</span></h2>
                <p class="text-white-50 mb-0">Read what each candidate stands for before casting your vote</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                @if ($this->isEligibleToEdit)
                    <button class="btn btn-glow btn-sm" data-bs-toggle="modal" data-bs-target="#editMyPlatformModal">
                        <i class="bi bi-pencil-square me-2"></i>Edit My Platform
                    </button>
                @endif
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
        </div>

        <div class="d-flex gap-2 flex-wrap mb-4">
            @foreach ($this->positionsList as $pos)
                <button wire:click="selectPosition('{{ $pos }}')"
                    class="tab-custom {{ $selectedPosition === $pos ? 'active' : '' }}">
                    {{ $pos }}
                </button>
            @endforeach
        </div>

        <div class="row g-4">
            @forelse($this->filteredCandidates as $candidate)
                @php
                    $approvedPlatform = $candidate->platforms->first();
                    $displayTitle = $approvedPlatform ? $approvedPlatform->title : 'Platform Overview';
                    $displayVision = $approvedPlatform ? $approvedPlatform->vision : $candidate->slogan;
                    $displayMission = $approvedPlatform ? $approvedPlatform->mission : $candidate->bio;
                    $displayGoals =
                        $approvedPlatform && is_array($approvedPlatform->goals) ? $approvedPlatform->goals : [];
                @endphp
                <div class="col-lg-6" wire:key="platform-{{ $candidate->id }}">
                    <div class="glass-card platform-card p-4 h-100">
                        <div class="d-flex gap-3 mb-3">
                            <div class="platform-avatar d-flex align-items-center justify-content-center fw-bold text-white"
                                style="background: {{ $this->getAvatarColor($candidate->id) }}; width: 60px; height: 60px; border-radius: 15px; border: 2px solid rgba(255,255,255,0.1); overflow: hidden;">
                                @if ($candidate->student?->profile_photo_path)
                                    <img src="{{ asset('storage/' . $candidate->student->profile_photo_path) }}"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                @else
                                    {{ strtoupper(substr($candidate->student?->first_name ?? 'A', 0, 1)) }}{{ strtoupper(substr($candidate->student?->last_name ?? 'A', 0, 1)) }}
                                @endif
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-white">{{ $candidate->student?->first_name }}
                                    {{ $candidate->student?->last_name }}</h5>
                                <small class="text-white-50">{{ $candidate->position?->name }} •
                                    {{ $candidate->party_name ?? 'Independent' }}</small>
                            </div>
                        </div>

                        <div class="glass p-3 mb-3 rounded-3" style="background: rgba(255,255,255,0.03);">
                            <h6 class="fw-bold mb-1 text-accent" style="font-size: 0.85rem;"><i
                                    class="bi bi-quote me-1"></i>{{ $displayTitle }}</h6>
                            <p class="text-white-50 mb-0 small fst-italic">"{{ $displayVision ?? 'Ready to serve.' }}"
                            </p>
                        </div>

                        <h6 class="fw-semibold mb-2 small text-white">Primary Goals</h6>
                        <ul class="text-white-50 small mb-4">
                            @forelse($displayGoals as $goal)
                            <li>{{ $goal }}</li> @empty <li>To be announced.</li>
                            @endforelse
                        </ul>

                        <div class="d-flex gap-2 mt-auto">
                            <button class="btn btn-outline-glow btn-sm flex-grow-1" data-bs-toggle="modal"
                                data-bs-target="#manifestoModal{{ $candidate->id }}"><i
                                    class="bi bi-file-earmark-text me-1"></i>Full Manifesto</button>
                            <a href="{{ url('/students/cast-vote') }}" wire:navigate
                                class="btn btn-glow btn-sm px-4"><i class="bi bi-check2-square me-1"></i>Vote</a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5">
                    <p class="text-white-50">No candidates found for your course and position.</p>
                </div>
            @endforelse
        </div>
    </main>

    @if ($this->isEligibleToEdit)
        <div class="modal fade" id="editMyPlatformModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                <div class="modal-content bg-dark text-white border-accent shadow-glow"
                    style="display: flex; flex-direction: column; max-height: 90vh;">
                    <div class="modal-header border-white-10 flex-shrink-0">
                        <h5 class="modal-title fw-bold">Update Campaign Platform</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body custom-scrollbar" style="flex: 1 1 auto; overflow-y: auto; padding: 1.5rem;">
                        <form wire:submit.prevent="updatePlatform" id="platformForm">
                            <div class="mb-3">
                                <label class="form-label small text-white-50">Platform Title</label>
                                <input type="text" wire:model="platform_title"
                                    class="form-control bg-dark border-white-10 text-white shadow-none">
                                @error('platform_title')
                                    <span class="text-danger tiny d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label small text-white-50">Vision Statement</label>
                                <input type="text" wire:model="vision"
                                    class="form-control bg-dark border-white-10 text-white shadow-none">
                                @error('vision')
                                    <span class="text-danger tiny d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="form-label small text-white-50">Mission (Manifesto)</label>
                                <textarea wire:model="mission" rows="4" class="form-control bg-dark border-white-10 text-white shadow-none"></textarea>
                                @error('mission')
                                    <span class="text-danger tiny d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label
                                    class="form-label small text-white-50 fw-bold border-bottom border-white-10 pb-2 d-block">Goals</label>
                                @foreach ($goals as $index => $goal)
                                    <div class="d-flex gap-2 mb-2" wire:key="goal-{{ $index }}">
                                        <div class="flex-grow-1">
                                            <input type="text" wire:model="goals.{{ $index }}"
                                                class="form-control bg-dark border-white-10 text-white shadow-none">
                                            @error("goals.$index")
                                                <span class="text-danger tiny">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <button type="button" wire:click="removeGoal({{ $index }})"
                                            class="btn btn-outline-danger border-white-10 h-100"><i
                                                class="bi bi-trash"></i></button>
                                    </div>
                                @endforeach
                                <button type="button" wire:click="addGoal" class="btn btn-outline-glow btn-sm"><i
                                        class="bi bi-plus me-1"></i>Add Goal</button>
                                @error('goals')
                                    <span class="text-danger tiny d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label
                                    class="form-label small text-white-50 fw-bold border-bottom border-white-10 pb-2 d-block">Action
                                    Plans</label>
                                @foreach ($action_plans as $index => $plan)
                                    <div class="d-flex gap-2 mb-2" wire:key="action-plan-{{ $index }}">
                                        <div class="flex-grow-1">
                                            <input type="text" wire:model="action_plans.{{ $index }}"
                                                class="form-control bg-dark border-white-10 text-white shadow-none">
                                            @error("action_plans.$index")
                                                <span class="text-danger tiny">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <button type="button" wire:click="removeActionPlan({{ $index }})"
                                            class="btn btn-outline-danger border-white-10 h-100"><i
                                                class="bi bi-trash"></i></button>
                                    </div>
                                @endforeach
                                <button type="button" wire:click="addActionPlan"
                                    class="btn btn-outline-glow btn-sm"><i class="bi bi-plus me-1"></i>Add Action
                                    Plan</button>
                                @error('action_plans')
                                    <span class="text-danger tiny d-block mt-1">{{ $message }}</span>
                                @enderror
                            </div>
                        </form>
                    </div>

                    <div class="modal-footer border-white-10 flex-shrink-0">
                        <button type="button" class="btn btn-outline-glow btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" form="platformForm" class="btn btn-glow btn-sm">
                            <span wire:loading.remove wire:target="updatePlatform">Submit for Review</span>
                            <span wire:loading wire:target="updatePlatform"><i
                                    class="bi bi-arrow-repeat spin-animation"></i> Submitting...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<script>
    window.addEventListener('swal', event => {
        let data = event.detail[0] || event.detail;
        Swal.fire({
            title: data.title,
            text: data.text,
            icon: data.icon,
            background: "#1a222c",
            color: "#fff",
            confirmButtonColor: "var(--accent-color)"
        });
    });
</script>

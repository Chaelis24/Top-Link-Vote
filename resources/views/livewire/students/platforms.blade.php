<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Title, Computed, Url};
use Illuminate\Support\Facades\{Auth, Session, DB};
use App\Models\{Candidate, Position};

new #[Layout('layouts.app')] #[Title('Platforms')] class extends Component {
    #[Url]
    public string $selectedPosition = 'All Positions';

    public $slogan = '';
    public $bio = '';

    public function mount()
    {
        if (Auth::user()->hasRole('Candidate')) {
            $candidate = Auth::user()->candidate;
            if ($candidate) {
                $this->slogan = $candidate->slogan;
                $this->bio = $candidate->bio;
            }
        }
    }

    /**
     * Kunin ang mga positions na may kandidato sa course ng voter.
     */
    #[Computed]
    public function positionsList()
    {
        $voterCourse = Auth::user()->student->course;

        return collect(['All Positions'])->concat(
            Position::whereHas('candidates', function ($q) use ($voterCourse) {
                $q->where('status', 'approved')->whereHas('student', fn($sq) => $sq->where('course', $voterCourse))->whereHas('student.user.roles', fn($rq) => $rq->where('name', 'Candidate'));
            })->pluck('name'),
        );
    }

    /**
     * Filtered candidates based on voter's course and role.
     */
    #[Computed]
    public function filteredCandidates()
    {
        $voterCourse = Auth::user()->student->course;

        return Candidate::with(['student.user', 'position'])
            ->where('status', 'approved')
            ->whereHas('student', function ($query) use ($voterCourse) {
                $query->where('course', $voterCourse);
            })
            ->whereHas('student.user.roles', function ($query) {
                $query->where('name', 'Candidate');
            })
            ->when($this->selectedPosition !== 'All Positions', function ($query) {
                $query->whereHas('position', fn($q) => $q->where('name', $this->selectedPosition));
            })
            ->get();
    }

    public function updatePlatform()
    {
        if (!auth()->user()->hasRole('Candidate')) {
            return $this->dispatch('swal', [
                'title' => 'Unauthorized',
                'text' => 'You do not have permission to edit platforms.',
                'icon' => 'error',
            ]);
        }

        $candidate = Auth::user()->candidate;

        $this->validate([
            'slogan' => 'required|string|max:255',
            'bio' => 'required|string|min:10',
        ]);

        $candidate->update([
            'slogan' => $this->slogan,
            'bio' => $this->bio,
        ]);

        $this->dispatch('swal', [
            'title' => 'Success!',
            'text' => 'Your platform has been updated.',
            'icon' => 'success',
        ]);
    }

    public function selectPosition($pos)
    {
        $this->selectedPosition = $pos;
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
        {{-- Top Bar --}}
        <div class="topbar">
            <div>
                <h2>Candidate <span>Platforms</span></h2>
                <p class="text-white-50 mb-0">Read what each candidate stands for before casting your vote</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                @if (auth()->user()->hasRole('Candidate'))
                    <button class="btn btn-glow btn-sm" data-bs-toggle="modal" data-bs-target="#editMyPlatformModal">
                        <i class="bi bi-pencil-square me-2"></i>Edit My Platform
                    </button>
                @endif
                <a href="/students/profile" wire:navigate class="text-decoration-none">
                    <div class="avatar-circle overflow-hidden">
                        @if (auth()->user()->student->profile_photo_path)
                            <img src="{{ asset('storage/' . auth()->user()->student->profile_photo_path) }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <i class="bi bi-person-fill text-white"></i>
                        @endif
                    </div>
                </a>
            </div>
        </div>

        {{-- Position Tabs --}}
        <div class="d-flex gap-2 flex-wrap mb-4 fade-in-up delay-1">
            @foreach ($this->positionsList as $pos)
                <button wire:click="selectPosition('{{ $pos }}')"
                    class="tab-custom {{ $selectedPosition === $pos ? 'active' : '' }}">
                    {{ $pos }}
                </button>
            @endforeach
        </div>

        {{-- Platform Cards --}}
        <div class="row g-4">
            @forelse($this->filteredCandidates as $candidate)
                <div class="col-lg-6 fade-in-up delay-2" wire:key="platform-{{ $candidate->id }}">
                    <div class="glass-card platform-card p-4 h-100">
                        <div class="d-flex gap-3 mb-3">
                            <div class="platform-avatar d-flex align-items-center justify-content-center fw-bold text-white"
                                style="background: var(--accent); width: 60px; height: 60px; border-radius: 15px; flex-shrink: 0; border: 2px solid rgba(255,255,255,0.1);">
                                {{ strtoupper(substr($candidate->student->first_name, 0, 1)) }}{{ strtoupper(substr($candidate->student->last_name, 0, 1)) }}
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-white">{{ $candidate->student->first_name }}
                                    {{ $candidate->student->last_name }}</h5>
                                <small class="text-white-50">{{ $candidate->position->name }} •
                                    {{ $candidate->party_name ?? 'Independent' }}</small>
                                <div class="d-flex gap-2 mt-2 flex-wrap">
                                    <span class="badge rounded-pill bg-dark text-white-50 border border-white-10"
                                        style="font-size: 0.7rem;">
                                        <i class="bi bi-tag-fill me-1"></i>{{ $candidate->course }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="glass p-3 mb-3 rounded-3" style="background: rgba(255,255,255,0.03);">
                            <h6 class="fw-bold mb-1 text-accent" style="font-size: 0.85rem;">
                                <i class="bi bi-quote me-1"></i>Vision Statement
                            </h6>
                            <p class="text-white-50 mb-0 small fst-italic">"{{ $candidate->slogan }}"</p>
                        </div>

                        <h6 class="fw-semibold mb-3 small text-white"><i
                                class="bi bi-list-check me-2 text-accent"></i>Key Platform Points</h6>

                        <div class="mb-4">
                            <p class="text-white-50 small mb-0">{{ Str::limit($candidate->bio, 180) }}</p>
                        </div>

                        <div class="d-flex gap-2 mt-auto">
                            <button class="btn btn-outline-glow btn-sm flex-grow-1" data-bs-toggle="modal"
                                data-bs-target="#manifestoModal{{ $candidate->id }}">
                                <i class="bi bi-file-earmark-text me-1"></i>Full Manifesto
                            </button>
                            <a href="{{ url('/students/cast-vote') }}" wire:navigate class="btn btn-glow btn-sm px-4">
                                <i class="bi bi-check2-square me-1"></i>Vote
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Manifesto Modal --}}
                <div class="modal fade" id="manifestoModal{{ $candidate->id }}" tabindex="-1" wire:ignore.self>
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content bg-dark text-white border-white-10">
                            <div class="modal-header border-white-10">
                                <h5 class="modal-title fw-bold">Detailed Manifesto</h5>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <h4 class="text-accent mb-1">{{ $candidate->student->first_name }}
                                    {{ $candidate->student->last_name }}</h4>
                                <p class="small text-white-50 mb-4">{{ $candidate->position->name }}</p>

                                <h6 class="fw-bold text-white small text-uppercase">Vision</h6>
                                <p class="text-white-50 mb-4">"{{ $candidate->slogan }}"</p>

                                <h6 class="fw-bold text-white small text-uppercase">Full Details</h6>
                                <p class="text-white-50 lh-lg" style="white-space: pre-wrap;">{{ $candidate->bio }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5">
                    <i class="bi bi-person-exclamation fs-1 text-white-50"></i>
                    <p class="text-white-50 mt-2">No candidates found for your course and position.</p>
                </div>
            @endforelse
        </div>
    </main>

    {{-- Edit Modal --}}
    @if (auth()->user()->hasRole('Candidate'))
        <div class="modal fade" id="editMyPlatformModal" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content bg-dark text-white border-accent shadow-glow">
                    <form wire:submit.prevent="updatePlatform">
                        <div class="modal-header border-white-10">
                            <h5 class="modal-title fw-bold">Update Your Campaign Platform</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label small text-white-50">Campaign Slogan (Vision)</label>
                                <input type="text" wire:model="slogan"
                                    class="form-control bg-dark border-white-10 text-white shadow-none">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-white-50">Full Manifesto (Detailed Plans)</label>
                                <textarea wire:model="bio" rows="6" class="form-control bg-dark border-white-10 text-white shadow-none"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-white-10">
                            <button type="button" class="btn btn-outline-glow btn-sm"
                                data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-glow btn-sm" data-bs-dismiss="modal">Save
                                Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Platforms')] class extends Component {
    public string $selectedPosition = 'All Positions';

    public array $positions = ['All Positions', 'President', 'Vice President', 'Secretary', 'Treasurer'];

    // In a real app, you would use protected $candidates = [];
    // and fetch them in mount() or via a computed property.
    public array $candidates = [
        [
            'name' => 'Maria Santos',
            'position' => 'President',
            'party' => 'Unity Party',
            'initials' => 'MS',
            'color' => '#388E3C',
            'tags' => ['Education', 'Innovation', 'Community'],
            'vision' => 'A student body where every voice matters, innovation thrives, and transparency leads the way forward.',
            'points' => [['title' => 'Digital Transformation', 'desc' => 'Implement a student portal for real-time tracking.', 'icon_bg' => 'rgba(56, 142, 60, 0.15)', 'icon_color' => 'var(--accent)'], ['title' => 'Mental Health Program', 'desc' => 'Establish free counseling services.', 'icon_bg' => 'rgba(103, 58, 183, 0.15)', 'icon_color' => 'var(--purple)']],
        ],
    ];

    /**
     * Filters the candidates based on the selected tab.
     */
    public function filteredCandidates()
    {
        if ($this->selectedPosition === 'All Positions') {
            return $this->candidates;
        }

        return array_filter($this->candidates, function ($candidate) {
            return $candidate['position'] === $this->selectedPosition;
        });
    }

    /**
     * Update the active position filter.
     */
    public function selectPosition($pos)
    {
        $this->selectedPosition = $pos;
    }

    /**
     * Standard Logout Logic
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
        {{-- Top Bar --}}
        <div class="topbar" wire:key="persistent-topbar-header">
            <div>
                <h2>Candidate <span>Platforms</span></h2>
                <p class="text-white-50 mb-0">Read what each candidate stands for before casting your vote</p>
            </div>
            <a href="/students/profile" wire:navigate class="text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-circle overflow-hidden">
                        @if ($photo ?? '')
                            <img src="{{ $photo->temporaryUrl() }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @elseif($profile_photo_path ?? '')
                            <img src="{{ asset('storage/' . $profile_photo_path) }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <i class="bi bi-person-fill text-white"></i>
                        @endif
                    </div>
                </div>
            </a>
        </div>

        {{-- Position Tabs --}}
        <div class="d-flex gap-2 flex-wrap mb-4 fade-in-up delay-1">
            @foreach ($positions as $pos)
                <button wire:click="selectPosition('{{ $pos }}')"
                    class="tab-custom {{ $selectedPosition === $pos ? 'active' : '' }}">
                    {{ $pos }}
                </button>
            @endforeach
        </div>

        {{-- Platform Cards --}}
        <div class="row g-4">
            @forelse($this->filteredCandidates() as $candidate)
                <div class="col-lg-6 fade-in-up delay-2" wire:key="{{ $candidate['name'] }}">
                    <div class="glass-card platform-card p-4 h-100">
                        <div class="d-flex gap-3 mb-3">
                            <div class="platform-avatar d-flex align-items-center justify-content-center fw-bold text-white"
                                style="background: {{ $candidate['color'] }}; width: 60px; height: 60px; border-radius: 15px; flex-shrink: 0; border: 2px solid rgba(255,255,255,0.1);">
                                {{ $candidate['initials'] }}
                            </div>
                            <div>
                                <h5 class="fw-bold mb-0 text-white">{{ $candidate['name'] }}</h5>
                                <small class="text-white-50">{{ $candidate['position'] }} •
                                    {{ $candidate['party'] }}</small>
                                <div class="d-flex gap-2 mt-2 flex-wrap">
                                    @foreach ($candidate['tags'] as $tag)
                                        <span class="badge rounded-pill bg-dark text-white-50 border border-white-10"
                                            style="font-size: 0.7rem;">
                                            <i class="bi bi-tag-fill me-1"></i>{{ $tag }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="glass p-3 mb-3 rounded-3" style="background: rgba(255,255,255,0.03);">
                            <h6 class="fw-bold mb-1 text-accent" style="font-size: 0.85rem;">
                                <i class="bi bi-quote me-1"></i>Vision Statement
                            </h6>
                            <p class="text-white-50 mb-0 small fst-italic">"{{ $candidate['vision'] }}"</p>
                        </div>

                        <h6 class="fw-semibold mb-3 small text-white"><i
                                class="bi bi-list-check me-2 text-accent"></i>Key Platform Points</h6>

                        @foreach ($candidate['points'] as $index => $point)
                            <div class="d-flex gap-3 mb-3">
                                <div class="rounded-3 d-flex align-items-center justify-content-center fw-bold"
                                    style="background: {{ $point['icon_bg'] }}; color: {{ $point['icon_color'] }}; width: 32px; height: 32px; flex-shrink: 0;">
                                    {{ $index + 1 }}
                                </div>
                                <div>
                                    <div class="fw-semibold text-white small">{{ $point['title'] }}</div>
                                    <small class="text-white-50 d-block">{{ $point['desc'] }}</small>
                                </div>
                            </div>
                        @endforeach

                        <div class="d-flex gap-2 mt-auto">
                            <button class="btn btn-outline-glow btn-sm flex-grow-1" data-bs-toggle="modal"
                                data-bs-target="#fullManifestoModal">
                                <i class="bi bi-file-earmark-text me-1"></i>Full Manifesto
                            </button>
                            <a href="{{ url('/students/cast-vote') }}" wire:navigate class="btn btn-glow btn-sm px-4">
                                <i class="bi bi-check2-square me-1"></i>Vote
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12 text-center py-5">
                    <i class="bi bi-person-exclamation fs-1 text-white-50"></i>
                    <p class="text-white-50 mt-2">No candidates found for this position.</p>
                </div>
            @endforelse
        </div>

        {{-- Floating Compare Button --}}
        <button class="btn btn-glow compare-btn fixed-bottom m-4 ms-auto fade-in-up delay-3"
            style="width: fit-content;">
            <i class="bi bi-arrows-angle-expand me-2"></i>Compare Candidates
        </button>
    </main>

    {{-- Full Manifesto Modal --}}
    <div class="modal fade" id="fullManifestoModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-white border-white-10">
                <div class="modal-header border-white-10">
                    <h5 class="modal-title fw-bold">Detailed Manifesto</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-white-50 small">Detailed information will be dynamically loaded here.</p>
                </div>
            </div>
        </div>
    </div>
</div>

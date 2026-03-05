<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-wrapper">
            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="brand-logo">
            <div class="brand-text">
                <span class="top-link">TOP LINK</span>
                <span class="global">GLOBAL</span>
                <div class="college-row">
                    <span class="college">COLLEGE</span>
                    <span class="inc">inc.</span>
                </div>
            </div>
        </div>
    </div>

    <ul class="sidebar-nav">
        {{-- Dashboard --}}
        <li class="nav-item">
            <a href="{{ url('students/dashboard') }}"
                class="nav-link {{ request()->is('students/dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2-fill"></i>
                <div>
                    Dashboard
                </div>
            </a>
        </li>

        {{-- Profiles & Candidates --}}
        <li class="nav-item">
            <a href="{{ url('students/profile-candidates') }}"
                class="nav-link {{ request()->is('students/profile-candidate*') ? 'active' : '' }}">
                <i class="bi bi-people-fill"></i>
                <div>
                    Profiles & Candidates
                    <span class="nav-desc">View Candidates</span>
                </div>
            </a>
        </li>

        <li class="nav-item">
            <a href="{{ url('students/platforms') }}"
                class="nav-link {{ request()->is('students/platforms*') ? 'active' : '' }}">
                <i class="bi bi-megaphone-fill"></i>
                <div>
                    Platforms
                    <span class="nav-desc">View Platforms</span>
                </div>
            </a>
        </li>

        {{-- Cast Your Vote --}}
        <li class="nav-item">
            <a href="{{ url('students/cast-vote') }}"
                class="nav-link {{ request()->is('students/cast-vote*') ? 'active' : '' }}">
                <i class="bi bi-check2-circle"></i>
                <div>
                    Cast Your Vote
                    <span class="nav-desc">Vote Now</span>
                </div>
            </a>
        </li>
    </ul>

    <div class="mx-3 mt-4 p-3 glass" style="border-radius: 12px;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="pulse-dot" style="background: var(--success);"></span>
            <span class="badge badge-status badge-open">Election Open</span>
        </div>
        <small class="text-white-50" style="font-size: 0.75rem;">Voting ends: Dec 15, 2025</small>
    </div>

    <div class="mt-auto px-3 pt-4" style="position: absolute; bottom: 24px; left: 0; right: 0;">
        <button wire:click="logout" class="btn btn-outline-glow w-100">
            <i class="bi bi-box-arrow-left me-2"></i>Logout
        </button>
    </div>
</aside>

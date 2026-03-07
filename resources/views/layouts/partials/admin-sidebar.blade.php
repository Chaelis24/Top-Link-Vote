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
            <a href="{{ url('admin/dashboard') }}"
                class="nav-link {{ request()->is('admin/dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-1x2-fill"></i>
                <div>Dashboard</div>
            </a>
        </li>

        {{-- Candidates --}}
        <li class="nav-item">
            <a href="{{ url('admin/candidates') }}"
                class="nav-link {{ request()->is('admin/candidates*') ? 'active' : '' }}">
                <i class="bi bi-people-fill"></i>
                <div>
                    Manage Candidates
                    <span class="nav-desc">View Candidate</span>
                </div>
            </a>
        </li>

        {{-- Profiles --}}
        <li class="nav-item">
            <a href="{{ url('admin/profiles') }}"
                class="nav-link {{ request()->is('admin/profiles*') ? 'active' : '' }}">
                <i class="bi bi-person-gear"></i>
                <div>
                    Manage Profiles
                    <span class="nav-desc">Edit Profile, Platform</span>
                </div>
            </a>
        </li>

        {{-- Platforms --}}
        <li class="nav-item">
            <a href="{{ url('admin/platforms') }}"
                class="nav-link {{ request()->is('admin/platforms*') ? 'active' : '' }}">
                <i class="bi bi-megaphone-fill"></i>
                <div>
                    Manage Platforms
                    <span class="nav-desc">Campaign Platforms</span>
                </div>
            </a>
        </li>

        {{-- Positions --}}
        <li class="nav-item">
            <a href="{{ url('admin/positions') }}"
                class="nav-link {{ request()->is('admin/positions*') ? 'active' : '' }}">
                <i class="bi bi-diagram-3-fill"></i>
                <div>
                    Manage Position
                    <span class="nav-desc">Add/Edit Roles</span>
                </div>
            </a>
        </li>

        {{-- Students --}}
        <li class="nav-item">
            <a href="{{ url('admin/students') }}"
                class="nav-link {{ request()->is('admin/students*') ? 'active' : '' }}">
                <i class="bi bi-person-check-fill"></i>
                <div>
                    Manage Students
                    <span class="nav-desc">View/Update list</span>
                </div>
            </a>
        </li>

        {{-- Election Cycle --}}
        <li class="nav-item">
            <a href="{{ url('admin/election-cycle') }}"
                class="nav-link {{ request()->is('admin/election-cycle*') ? 'active' : '' }}">
                <i class="bi bi-calendar-event-fill"></i>
                <div>
                    Election Cycle
                    <span class="nav-desc">Set Dates, Status</span>
                </div>
            </a>
        </li>
    </ul>

    {{-- Logout --}}
    <div class="mt-auto px-3 pt-4" style="position: absolute; bottom: 24px; left: 0; right: 0;">
        <button wire:click="logout" class="btn btn-outline-glow w-100">
            <i class="bi bi-box-arrow-left me-2"></i>Logout
        </button>
    </div>
</aside>

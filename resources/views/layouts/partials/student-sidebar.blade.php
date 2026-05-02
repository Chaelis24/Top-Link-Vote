<div x-data="{ studentMobileOpen: false }">

    <button class="mobile-toggle" @click="studentMobileOpen = true" x-show="!studentMobileOpen" x-cloak>
        <i class="bi bi-list"></i>
    </button>

    <div class="sidebar-overlay" :class="{ 'active': studentMobileOpen }" x-show="studentMobileOpen"
        @click="studentMobileOpen = false" x-cloak>
    </div>

    <aside class="sidebar-modern" :class="{ 'show': studentMobileOpen }">
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

        <nav class="sidebar-content">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="{{ url('students/dashboard') }}" wire:navigate
                        class="nav-link {{ request()->is('students/dashboard') ? 'active' : '' }}">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ url('students/profile-platforms') }}" wire:navigate
                        class="nav-link {{ request()->is('students/profile-platforms*') ? 'active' : '' }}">
                        <i class="bi bi-person-vcard"></i>
                        <span>Profile & Platforms</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ url('students/cast-vote') }}" wire:navigate
                        class="nav-link {{ request()->is('students/cast-vote*') ? 'active' : '' }}">
                        <i class="bi bi-check2-square"></i>
                        <span>Cast Your Vote</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer" x-data="{ open: false }" style="position: relative;">
            <div class="footer-main-content d-flex align-items-center clickable-card" @click="open = !open"
                style="cursor: pointer;">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-wrapper" style="position: relative;">
                        @if ($profile_photo_path)
                            <img src="{{ asset('storage/' . $profile_photo_path) }}" class="user-avatar" alt="User">
                        @else
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Student') }}&background=10B981&color=fff"
                                class="user-avatar" alt="User">
                        @endif
                        <div class="status-indicator"
                            style="position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; background: #10B981; border: 2px solid #c0c0c0; border-radius: 50%;">
                        </div>
                    </div>

                    <div class="user-details">
                        <p class="user-name text-truncate" style="max-width: 100px;">
                            {{ auth()->user()->name ?? 'Student' }}</p>
                        <p class="user-role text-uppercase" style="font-size: 0.6rem; font-weight: 700;">View Profile
                        </p>
                    </div>
                </div>

                <i class="bi bi-chevron-up ms-auto transition-transform" :class="{ 'rotate-180': open }"></i>
            </div>

            <div x-show="open" @click.away="open = false" x-cloak x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100" class="dropdown-menu-custom">

                <a href="/students/profile" wire:navigate class="dropdown-item">
                    <i class="bi bi-person-circle"></i> Profile
                </a>

                <hr class="dropdown-divider" style="border-top: 1px solid rgba(0,0,0,0.1); margin: 8px 0;">

                <button wire:click="logout" class="dropdown-item text-danger w-100 text-start border-0 bg-transparent">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </div>
        </div>
    </aside>
</div>

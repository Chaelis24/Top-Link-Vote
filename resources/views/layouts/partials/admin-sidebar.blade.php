<div x-data="{ mobileMenuOpen: false }">

    <button class="admin-mobile-btn" @click="mobileMenuOpen = true" x-show="!mobileMenuOpen" x-cloak>
        <i class="bi bi-list"></i>
    </button>

    <div class="admin-overlay-screen" :class="{ 'active': mobileMenuOpen }" x-show="mobileMenuOpen"
        @click="mobileMenuOpen = false" x-cloak>
    </div>

    <aside class="admin-side-wrapper" :class="{ 'show': mobileMenuOpen }">
        <div class="admin-brand-box">
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
            <ul class="admin-nav-list">
                <li class="admin-nav-item">
                    <a href="{{ url('admin/dashboard') }}" wire:navigate
                        class="admin-nav-link {{ request()->is('admin/dashboard') ? 'active' : '' }}">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="{{ url('admin/candidates') }}" wire:navigate
                        class="admin-nav-link {{ request()->is('admin/candidates*') ? 'active' : '' }}">
                        <i class="bi bi-person-badge-fill"></i>
                        <span>Candidates Profile</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="{{ url('admin/platforms') }}" wire:navigate
                        class="admin-nav-link {{ request()->is('admin/platforms*') ? 'active' : '' }}">
                        <i class="bi bi-megaphone-fill"></i>
                        <span>Platforms</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="{{ url('admin/positions') }}" wire:navigate
                        class="admin-nav-link {{ request()->is('admin/positions*') ? 'active' : '' }}">
                        <i class="bi bi-diagram-3-fill"></i>
                        <span>Positions</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="{{ url('admin/students') }}" wire:navigate
                        class="admin-nav-link {{ request()->is('admin/students*') ? 'active' : '' }}">
                        <i class="bi bi-person-check-fill"></i>
                        <span>Students List</span>
                    </a>
                </li>
                <li class="admin-nav-item">
                    <a href="{{ url('admin/election-cycle') }}" wire:navigate
                        class="admin-nav-link {{ request()->is('admin/election-cycle*') ? 'active' : '' }}">
                        <i class="bi bi-calendar-event-fill"></i>
                        <span>Election Cycle</span>
                    </a>
                </li>
            </ul>
        </nav>

        <div class="admin-footer-sec" x-data="{ adminOpen: false }" style="position: relative;">
            <div class="d-flex align-items-center" @click="adminOpen = !adminOpen"
                style="cursor: pointer; padding: 4px;">
                <div class="d-flex align-items-center gap-2">
                    <div style="position: relative;">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&background=1e3a8a&color=fff"
                            style="width: 32px; height: 32px; border-radius: 8px;" alt="Admin">
                        <div
                            style="position: absolute; bottom: -2px; right: -2px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; background: #10B981;">
                        </div>
                    </div>
                    <div class="admin-user-details">
                        <p class="admin-user-name text-truncate" style="max-width: 130px;font-size: 0.85rem;">
                            {{ auth()->user()->name ?? 'Admin' }}</p>
                        <p style="margin:0; font-size: 0.65rem; color: #1e3a8a; font-weight: 800;">View Profile</p>
                    </div>
                </div>
                <i class="bi bi-chevron-up ms-auto transition-transform" :class="{ 'rotate-180': adminOpen }"
                    style="font-size: 0.8rem;"></i>
            </div>

            <div x-show="adminOpen" @click.away="adminOpen = false" x-cloak
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100" class="admin-dropdown-box">

                <a href="/admin/settings" wire:navigate class="dropdown-item d-flex align-items-center gap-2"
                    style="text-decoration: none; color: #4b5563; padding: 8px 12px; font-size: 0.8rem;">
                    <i class="bi bi-gear-fill"></i> Settings
                </a>

                <hr style="margin: 8px 0; border-top: 1px solid #f3f4f6;">

                <button wire:click="logout"
                    class="dropdown-item text-danger w-100 text-start border-0 bg-transparent d-flex align-items-center gap-2"
                    style="padding: 8px 12px; font-size: 0.8rem;">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </div>
        </div>
    </aside>
</div>

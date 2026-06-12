<div x-data="{
    studentMobileOpen: false,
    currentPath: window.location.pathname
}" x-on:livewire:navigated.window="currentPath = window.location.pathname">
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
                    <a href="{{ url('students/dashboard') }}" wire:navigate class="nav-link"
                        :class="currentPath.includes('students/dashboard') ? 'active' : ''">
                        <i class="bi bi-grid-1x2-fill"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ url('students/profile-platforms') }}" wire:navigate class="nav-link"
                        :class="currentPath.includes('students/profile-platforms') ? 'active' : ''">
                        <i class="bi bi-person-vcard"></i>
                        <span>Platforms</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="{{ url('students/cast-vote') }}" wire:navigate class="nav-link"
                        :class="currentPath.includes('students/cast-vote') ? 'active' : ''">
                        <i class="bi bi-check2-square"></i>
                        <span>Cast Vote</span>
                    </a>
                </li>

                <li class="nav-item hidden lg:hidden" x-data="{ open: false }" style="position: relative;">
                    <a @click.prevent="open = !open" style="cursor: pointer;" class="nav-link"
                        :class="currentPath.split('?')[0] === '/students/profile' ? 'active' : ''">

                        @if ($profile_photo_path)
                            <img src="{{ asset('storage/' . $profile_photo_path) }}" class="user-avatar-mobile"
                                alt="Student">
                        @else
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Student') }}&background=10B981&color=fff"
                                class="user-avatar-mobile" alt="Student">
                        @endif

                        <span>Profile</span>
                    </a>

                    <div x-show="open" @click.away="open = false" x-cloak style="display: none;"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 transform scale-95"
                        x-transition:enter-end="opacity-100 transform scale-100"
                        class="dropdown-menu-custom mobile-dropdown">

                        <a href="/students/profile" wire:navigate
                            class="dropdown-item d-flex align-items-center gap-2 text-start"
                            style="padding: 8px 12px; font-size: 0.8rem; color: #4b5563; text-decoration: none;">
                            <i class="bi bi-person-circle"></i> My Profile
                        </a>
                        <hr style="margin: 4px 0; border-top: 1px solid #f3f4f6;">
                        <button wire:click="logout"
                            class="dropdown-item text-danger w-100 text-start border-0 bg-transparent d-flex align-items-center gap-2"
                            style="padding: 8px 12px; font-size: 0.8rem;">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </button>
                    </div>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer d-lg-block" x-data="{ open: false }" style="position: relative;">
            <div class="d-flex align-items-center" @click="open = !open" style="cursor: pointer;">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar-wrapper" style="position: relative;">
                        @if ($profile_photo_path)
                            <img src="{{ asset('storage/' . $profile_photo_path) }}" class="user-avatar" alt="Student"
                                style="object-fit: cover;">
                        @else
                            <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Student') }}&background=10B981&color=fff"
                                class="user-avatar" alt="Student">
                        @endif
                        <div class="status-indicator"
                            style="position: absolute; bottom: 0; right: 0; width: 12px; height: 12px; background: #10B981; border: 2px solid #c0c0c0; border-radius: 50%;">
                        </div>
                    </div>

                    <div class="user-details">
                        <p class="user-name text-truncate" style="max-width: 150px; font-size: 0.80rem;">
                            {{ auth()->user()->name ?? 'Student' }}</p>
                        <p class="user-role text-uppercase" style="font-size: 0.60rem; font-weight: 700;">View Profile
                        </p>
                    </div>
                </div>

                <i class="bi bi-chevron-up ms-auto transition-transform text-white" :class="{ 'rotate-180': open }"></i>
            </div>

            <div x-show="open" @click.away="open = false" x-cloak x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 transform scale-95"
                x-transition:enter-end="opacity-100 transform scale-100" class="dropdown-menu-custom">

                <a href="/students/profile" wire:navigate class="dropdown-item">
                    <i class="bi bi-person-circle"></i> My Profile
                </a>

                <hr class="dropdown-divider" style="border-top: 1px solid rgba(0,0,0,0.1); margin: 8px 0;">

                <button wire:click="logout" class="dropdown-item text-danger w-100 text-start border-0 bg-transparent">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </button>
            </div>
        </div>
    </aside>
</div>

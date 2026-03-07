<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] #[Title('Manage Profiles')] class extends Component {
    // Search state for filtering candidates
    public string $search = '';

    /**
     * Handle the admin logout logic.
     */
    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/', navigate: true);
    }

    /**
     * Placeholder for updating candidate profile information.
     */
    public function updateProfile()
    {
        // Add Validation and Update logic here
        $this->dispatch('notify', message: 'Profile updated successfully!', type: 'success');
    }

    /**
     * Placeholder for updating platform details.
     */
    public function updatePlatform()
    {
        // Add Platform update logic here
        $this->dispatch('notify', message: 'Platform details saved.', type: 'info');
    }
}; ?>

<div>
    {{-- Sidebar & Navigation --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar">
            <div>
                <h2>Manage <span>Profiles</span></h2>
                <p class="text-white-50 mb-0" style="font-size: 0.85rem;">Edit candidate profiles and platform details</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="search-wrap" style="width: 250px;">
                    <i class="bi bi-search"></i>
                    <input type="text" wire:model.live.debounce.300ms="search" class="search-glass"
                        placeholder="Search profiles...">
                </div>
            </div>
        </div>

        {{-- Profile Cards Grid --}}
        <div class="row g-4">
            {{-- Card 1: Juan Dela Cruz --}}
            <div class="col-md-6 col-lg-4 fade-in-up delay-1">
                <div class="glass-card profile-card">
                    <div class="profile-card-header green-bg">
                        <div class="profile-avatar">JD</div>
                    </div>
                    <div class="profile-card-body">
                        <h6 class="fw-bold mb-1">Juan Dela Cruz</h6>
                        <small class="text-white-50">Running for President</small>
                        <span class="badge badge-status badge-open d-block mx-auto mt-2"
                            style="width: fit-content;">Approved</span>

                        <div class="mt-3 text-start">
                            <div class="profile-info-item"><span class="text-white-50">Course</span><span
                                    class="fw-medium">BSIT - 4th Year</span></div>
                            <div class="profile-info-item"><span class="text-white-50">Party</span><span
                                    class="fw-medium" style="color: var(--accent);">Green Alliance</span></div>
                            <div class="profile-info-item"><span class="text-white-50">GPA</span><span
                                    class="fw-medium">1.50</span></div>
                        </div>

                        <div class="profile-actions">
                            <button class="btn btn-outline-glow btn-sm" data-bs-toggle="modal"
                                data-bs-target="#editProfileModal"><i class="bi bi-pencil me-1"></i>Edit</button>
                            <button class="btn btn-outline-glow btn-sm"
                                style="border-color: var(--purple); color: var(--purple);" data-bs-toggle="modal"
                                data-bs-target="#viewPlatformModal"><i
                                    class="bi bi-megaphone me-1"></i>Platform</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Card 3: Antonio Reyes (Pending Example) --}}
            <div class="col-md-6 col-lg-4 fade-in-up delay-3">
                <div class="glass-card profile-card">
                    <div class="profile-card-header green-bg">
                        <div class="profile-avatar">AR</div>
                    </div>
                    <div class="profile-card-body">
                        <h6 class="fw-bold mb-1">Antonio Reyes</h6>
                        <small class="text-white-50">Running for Vice President</small>
                        <span class="badge badge-status d-block mx-auto mt-2"
                            style="width: fit-content; background: rgba(253,203,110,0.15); color: var(--warning);">Pending</span>

                        <div class="mt-3 text-start">
                            <div class="profile-info-item"><span class="text-white-50">Course</span><span
                                    class="fw-medium">BSBA - 4th Year</span></div>
                            <div class="profile-info-item"><span class="text-white-50">Party</span><span
                                    class="fw-medium" style="color: var(--accent);">Green Alliance</span></div>
                            <div class="profile-info-item"><span class="text-white-50">GPA</span><span
                                    class="fw-medium">1.75</span></div>
                        </div>
                        <div class="profile-actions">
                            <button class="btn btn-outline-glow btn-sm"><i class="bi bi-pencil me-1"></i>Edit</button>
                            <button class="btn btn-outline-glow btn-sm"
                                style="border-color: var(--purple); color: var(--purple);"><i
                                    class="bi bi-megaphone me-1"></i>Platform</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Edit Profile Modal --}}
    <div class="modal fade modal-glass" id="editProfileModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-gear me-2" style="color: var(--accent);"></i>Edit
                        Profile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form wire:submit.prevent="updateProfile">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small">Full Name</label>
                                <input type="text" class="form-control-glass w-100" value="Juan Dela Cruz">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small">GPA</label>
                                <input type="text" class="form-control-glass w-100" value="1.50">
                            </div>
                            <div class="col-12">
                                <label class="form-label text-white-50 small">Bio / Tagline</label>
                                <textarea class="form-control-glass w-100" rows="3" placeholder="Enter candidate bio..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" wire:click="updateProfile" class="btn btn-glow btn-sm"
                        data-bs-dismiss="modal">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

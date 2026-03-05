<?php

use function Livewire\Volt\{state, layout, title};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

layout('layouts.app');
title('Manage Profiles - Admin');

state([
    'search' => '',
]);

$logout = function () {
    Auth::guard('web')->logout();
    Session::invalidate();
    Session::regenerateToken();
    return $this->redirect('/', navigate: true);
};

?>

<div>
    {{-- Sidebar & Navigation --}}
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.admin-sidebar')

    <main class="main-content">
        <div class="topbar" data-aos="fade-down">
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
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="100">
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

            {{-- Card 2: Maria Santos --}}
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card profile-card">
                    <div class="profile-card-header purple-bg">
                        <div class="profile-avatar"
                            style="background: linear-gradient(135deg, var(--purple), var(--purple-glow));">MS</div>
                    </div>
                    <div class="profile-card-body">
                        <h6 class="fw-bold mb-1">Maria Santos</h6>
                        <small class="text-white-50">Running for President</small>
                        <span class="badge badge-status badge-open d-block mx-auto mt-2"
                            style="width: fit-content;">Approved</span>

                        <div class="mt-3 text-start">
                            <div class="profile-info-item"><span class="text-white-50">Course</span><span
                                    class="fw-medium">BSA - 3rd Year</span></div>
                            <div class="profile-info-item"><span class="text-white-50">Party</span><span
                                    class="fw-medium" style="color: var(--purple);">Purple Wave</span></div>
                            <div class="profile-info-item"><span class="text-white-50">GPA</span><span
                                    class="fw-medium">1.25</span></div>
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

            {{-- Card 3: Antonio Reyes (Pending) --}}
            <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="300">
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
                    <form>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small">Full Name</label>
                                <input type="text" class="form-control-glass w-100" value="Juan Dela Cruz">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small">Student ID</label>
                                <input type="text" class="form-control-glass w-100" value="2024-00123">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small">GPA</label>
                                <input type="text" class="form-control-glass w-100" value="1.50">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-white-50 small">Position</label>
                                <select class="form-control-glass w-100">
                                    <option selected>President</option>
                                    <option>Vice President</option>
                                </select>
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
                    <button type="button" class="btn btn-glow btn-sm">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    {{-- View Platform Modal --}}
    <div class="modal fade modal-glass" id="viewPlatformModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-0">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="profile-avatar static-avatar">JD</div>
                        <div>
                            <h5 class="fw-bold mb-0">Juan Dela Cruz</h5>
                            <small class="text-white-50">President • Green Alliance</small>
                        </div>
                    </div>

                    <div class="vision-box mb-4">
                        <h6 class="fw-bold mb-2 text-accent small"><i class="bi bi-quote"></i> Vision Statement</h6>
                        <p class="mb-0 fst-italic text-white-50 small">"A transparent and innovative student government
                            that prioritizes education quality and student welfare."</p>
                    </div>

                    <h6 class="fw-bold mb-3 small"><i class="bi bi-list-check me-2 text-accent"></i>Platform Points
                    </h6>
                    <div class="platform-point-item mb-2">
                        <div class="point-number">1</div>
                        <div class="point-content">
                            <div class="fw-semibold small">Free Academic Tutoring Services</div>
                            <p class="text-white-50 mb-0 extra-small">Provide free peer tutoring across all
                                departments.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-glow btn-sm" data-bs-toggle="modal"
                        data-bs-target="#editPlatformFromProfileModal">Edit Platform</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Edit Platform Modal --}}
    <div class="modal fade modal-glass" id="editPlatformFromProfileModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title small"><i class="bi bi-pencil-square me-2 text-accent"></i>Edit Platform
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Vision Statement</label>
                            <textarea class="form-control-glass w-100" rows="3">A transparent and innovative student government...</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-white-50 small">Key Priorities (one per line)</label>
                            <textarea class="form-control-glass w-100" rows="5">Free academic tutoring services
Transparent budget allocation</textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-glow btn-sm"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-glow btn-sm">Save Platform</button>
                </div>
            </div>
        </div>
    </div>
</div>

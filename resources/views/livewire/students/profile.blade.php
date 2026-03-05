<?php

use function Livewire\Volt\{state, mount, layout, title, usesFileUploads};
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

usesFileUploads();
layout('layouts.app');
title('Student Profile');

state([
    'name' => '',
    'email' => '',
    'student_id' => '',
    'course' => '',
    'year_level' => '',
    'status' => '',

    'phone' => '',
    'address' => '',
    'birthday' => '',
    'gender' => '',
    'photo' => null,

    // Password state
    'current_password' => '',
    'new_password' => '',
    'new_password_confirmation' => '',
]);

mount(function () {
    $user = Auth::user()->load('student');
    $profile = $user->student;

    $this->name = $user->name;
    $this->email = $user->email;

    if ($profile) {
        $this->student_id = $profile->student_id;
        $this->course = $profile->course;
        $this->year_level = $profile->year_level;
        $this->photo = $profile->photo;
        $this->status = $profile->status;
    }
});

$saveProfile = function () {
    // $user = Auth::user();

    // $user->update([
    //     'name' => $this->name,
    //     'email' => $this->email,
    // ]);

    // $user->studentProfile()->update([
    //     'phone' => $this->phone,
    //     'address' => $this->address,
    //     'birthday' => $this->birthday,
    //     'gender' => $this->gender,
    // ]);

    // $this->validate([
    //     'name' => 'required|min:3',
    //     'email' => 'required|email',
    //     'phone' => 'required',
    //     'photo' => 'nullable|image|max:2048',
    // ]);

    // Dito ilalagay ang actual DB update logic
    // auth()->user()->update([...]);

    $this->dispatch('notify', message: 'Profile updated successfully!', type: 'success');
    $this->dispatch('close-modal');
};

$updatePassword = function () {
    $this->validate([
        'current_password' => 'required',
        'new_password' => ['required', 'confirmed', Password::defaults()],
    ]);

    // DB Logic for password change...

    $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
    $this->dispatch('notify', message: 'Password changed successfully!', type: 'success');
};

$logout = function () {
    Auth::guard('web')->logout();
    Session::invalidate();
    Session::regenerateToken();
    return $this->redirect('/', navigate: true);
};

?>

<div>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.student-sidebar')

    <main class="main-content">
        {{-- Top Bar --}}
        <div class="topbar" data-aos="fade-down">
            <div>
                <h2>My <span>Profile</span></h2>
                <p class="text-white-50 mb-0">View your student information and voting status</p>
            </div>
            <a href="/students/profile" wire:navigate class="text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-circle">
                            <i class="bi bi-person-fill text-white"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        {{-- Profile Header Card --}}
        <div class="glass-card profile-header mb-4" data-aos="fade-up">
            <div class="row align-items-center">
                <div class="col-md-auto text-center text-md-start mb-3 mb-md-0">
                    <div class="position-relative d-inline-block">
                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="profile-avatar-lg">
                        @else
                            <div
                                class="profile-avatar-lg d-flex align-items-center justify-content-center bg-accent fs-2 fw-bold text-white">
                                EO</div>
                        @endif
                        <span class="profile-badge verified"><i class="bi bi-check-lg"></i></span>
                    </div>
                </div>
                <div class="col-md">
                    <h3 class="fw-bold mb-1 text-white">{{ $name }}</h3>
                    <p class="text-white-50 mb-2">
                        <i class="bi bi-mortarboard-fill me-1 text-accent"></i>
                        {{ $course ?? 'N/A' }}
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <span
                            class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success-subtle px-3">Eligible
                            Voter</span>
                        <span
                            class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary-subtle px-3">S.Y.
                            {{ $year_level ?? 'N/A' }}
                        </span>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <button class="btn btn-outline-glow btn-sm" data-bs-toggle="modal"
                        data-bs-target="#editProfileModal">
                        <i class="bi bi-pencil-square me-1"></i>Edit Profile
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- Personal Info --}}
            <div class="col-lg-6" data-aos="fade-up">
                <div class="glass-card info-card h-100 p-4">
                    <h5 class="fw-bold mb-4 text-white"><i class="bi bi-person-vcard me-2 text-accent"></i>Personal Info
                    </h5>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon-box"><i class="bi bi-hash text-accent"></i></div>
                        <div>
                            <small class="text-white-50 d-block">Student ID</small>
                            <span class="text-white fw-semibold">{{ $student_id ?? '' }}</span>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon-box"><i class="bi bi-envelope text-purple"></i></div>
                        <div>
                            <small class="text-white-50 d-block">Email Address</small>
                            <span class="text-white fw-semibold">{{ $email ?? 'No email' }}</span>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon-box"><i class="bi bi-telephone text-accent"></i></div>
                        <div>
                            <small class="text-white-50 d-block">Contact</small>
                            <span class="text-white fw-semibold">{{ $phone ?? 'No Contact No.' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Voting Status & Timeline --}}
            <div class="col-lg-6" data-aos="fade-up">
                <div class="glass-card p-4 h-100">
                    <h5 class="fw-bold mb-4 text-white"><i class="bi bi-clock-history me-2 text-warning"></i>Recent
                        Activity</h5>
                    <div class="timeline">
                        @foreach (['Logged in today at 10:30 AM', 'Viewed Candidate Platforms yesterday'] as $activity)
                            <div class="d-flex gap-3 mb-3 pb-3 border-bottom border-white-5">
                                <div class="timeline-dot-sm"></div>
                                <span class="text-white-50 small">{{ $activity }}</span>
                            </div>
                        @endforeach
                    </div>
                    <a href="{{ url('students/cast-vote') }}" wire:navigate class="btn btn-glow w-100 mt-3">Go to Voting
                        Page</a>
                </div>
            </div>
        </div>
    </main>

    {{-- Edit Profile Modal --}}
    <div class="modal fade" id="editProfileModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-white-10" style="background: #16213e;">
                <div class="modal-header border-white-10">
                    <h5 class="modal-title fw-bold text-white">Edit Profile</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form wire:submit="saveProfile">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12 text-center mb-3">
                                <label for="photoUpload" style="cursor: pointer;">
                                    <div class="position-relative d-inline-block">
                                        @if ($photo)
                                            <img src="{{ $photo->temporaryUrl() }}"
                                                style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
                                        @else
                                            <div
                                                style="width: 100px; height: 100px; border-radius: 50%; background: #2a2a40; display: flex; align-items: center; justify-content: center;">
                                                <i class="bi bi-camera fs-2 text-white-50"></i>
                                            </div>
                                        @endif
                                    </div>
                                </label>
                                <input type="file" id="photoUpload" wire:model="photo" class="d-none">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small text-white-50">Full Name</label>
                                <input type="text" wire:model="name"
                                    class="form-control bg-dark text-white border-white-10">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small text-white-50">Email</label>
                                <input type="email" wire:model="email"
                                    class="form-control bg-dark text-white border-white-10">
                            </div>
                        </div>

                        <hr class="my-4 border-white-10">
                        <h6 class="text-purple small fw-bold mb-3">Security</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <input type="password" wire:model="new_password"
                                    class="form-control bg-dark text-white border-white-10"
                                    placeholder="New Password">
                            </div>
                            <div class="col-md-6">
                                <input type="password" wire:model="new_password_confirmation"
                                    class="form-control bg-dark text-white border-white-10"
                                    placeholder="Confirm New Password">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-white-10">
                        <button type="button" class="btn btn-outline-glow btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-glow btn-sm">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .info-icon-box {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .timeline-dot-sm {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent);
            margin-top: 6px;
        }

        .border-white-5 {
            border-color: rgba(255, 255, 255, 0.05) !important;
        }

        .border-white-10 {
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
    </style>
</div>

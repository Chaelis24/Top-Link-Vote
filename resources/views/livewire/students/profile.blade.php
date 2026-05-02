<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\{Layout, Title};
use Illuminate\Support\Facades\{Hash, Auth, Session};
use Illuminate\Validation\Rules\Password;
use Carbon\Carbon;

new #[Layout('layouts.app')] #[Title('My Profile')] class extends Component {
    use WithFileUploads;

    // User State
    public $name = '';
    public $email = '';
    public $student;

    // Student Profile State
    public $user_id;
    public $student_id = '';
    public $first_name = '';
    public $middle_name = '';
    public $last_name = '';
    public $suffix = '';
    public $course = '';
    public $status = '';
    public $year_level = '';
    public $phone = '';
    public $address = '';
    public $birthday = '';
    public $gender = '';
    public $photo = null;
    public $profile_photo_path = null;
    public $has_voted = false;
    public $voted_at = null;

    // Password State
    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';

    /**
     * Mount logic to populate the form
     */
    public function mount()
    {
        $user = Auth::user()?->load('student');
        $profile = $user?->student;

        $this->name = $user?->name ?? '';
        $this->email = $user?->email ?? '';

        if ($profile) {
            $this->user_id = $profile->user_id;
            $this->student_id = $profile->student_id;
            $this->first_name = $profile->first_name;
            $this->middle_name = $profile->middle_name;
            $this->last_name = $profile->last_name;
            $this->suffix = $profile->suffix;
            $this->course = $profile->course;
            $this->status = $profile->status;
            $this->year_level = $profile->year_level;
            $this->phone = $profile->phone;
            $this->address = $profile->address;
            $this->birthday = $profile->birthday ? date('Y-m-d', strtotime($profile->birthday)) : '';
            $this->gender = $profile->gender;
            $this->profile_photo_path = $profile->photo;
            $this->has_voted = (bool) $profile->has_voted;
            $this->voted_at = $profile->voted_at;
        }
    }

    /**
     * Save Profile Logic
     */
    public function saveProfile()
    {
        $user = Auth::user();

        $this->validate([
            'email' => 'required|email|unique:users,email,' . $user?->id,
            'phone' => 'nullable|numeric',
            'photo' => 'nullable|image|max:2048',
        ]);

        $user?->update([
            'email' => $this->email,
        ]);

        if ($user?->student) {
            $data = [
                'phone' => $this->phone,
            ];

            if ($this->photo && !is_string($this->photo)) {
                $data['photo'] = $this->photo->store('profile-photos', 'public');
                $this->profile_photo_path = $data['photo'];

                $this->reset('photo');
            }

            $user->student->update($data);
        }

        $this->dispatch('swal', [
            'title' => 'Profile Updated!',
            'text' => 'Your information has been saved successfully.',
            'icon' => 'success',
        ]);

        $this->dispatch('close-modal');
    }

    /**
     * Update Password Logic
     */
    public function updatePassword()
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'new_password' => ['required', 'confirmed', Password::defaults()],
        ]);

        Auth::user()?->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        $this->dispatch('swal', [
            'title' => 'Security Updated',
            'text' => 'Your password has been changed successfully.',
            'icon' => 'success',
        ]);

        $this->dispatch('close-modal');
    }

    /**
     * Logout Logic
     */
    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return redirect()->route('login');
    }
}; ?>
<div>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.student-sidebar')

    <main class="main-content">
        <div class="topbar" wire:key="persistent-topbar-header">
            <div>
                <h2 class="text-dark">My <span class="text-primary">Profile</span></h2>
                <p class="text-secondary mb-0">View your student information and voting status</p>
            </div>
        </div>

        <div class="glass-card profile-header m-2 m-md-4 fade-in-up delay-1 border-0 shadow-sm overflow-hidden">
            <div class="row align-items-center g-0">
                <!-- Avatar Section: p-3 sa mobile, p-4 sa desktop -->
                <div class="col-md-auto text-center text-md-start p-3 p-md-4">
                    <div class="position-relative d-inline-block">
                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="profile-avatar-lg border shadow-sm"
                                style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%;">
                        @elseif($profile_photo_path)
                            <img src="{{ asset('storage/' . $profile_photo_path) }}"
                                class="profile-avatar-lg border shadow-sm"
                                style="width: 80px; height: 80px; object-fit: cover; border-radius: 50%;">
                        @else
                            <div class="profile-avatar-lg d-flex align-items-center justify-content-center bg-emerald-light fw-bold text-primary border shadow-sm"
                                style="width: 80px; height: 80px; border-radius: 50%; font-size: 1.5rem;">
                                {{ strtoupper(substr($first_name ?: 'U', 0, 1) . substr($last_name ?: 'P', 0, 1)) }}
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Details Section: text-center sa mobile, text-start sa desktop -->
                <div class="col-md px-3 px-md-0 py-2 py-md-4 text-center text-md-start">
                    <h4 class="fw-bold mb-1 text-dark text-truncate">
                        {{ $first_name ?: 'Student' }}
                        {{ $middle_name ? substr($middle_name, 0, 1) . '.' : '' }}
                        {{ $last_name ?: '' }}
                        {{ $suffix ?? '' }}
                    </h4>
                    <p class="text-secondary mb-2 fw-semibold small">
                        <i class="bi bi-mortarboard-fill me-1 text-primary"></i>
                        <span class="d-inline-block">{{ $course ?: 'N/A' }}</span>
                        <span class="mx-1 d-none d-md-inline">|</span>
                        <br class="d-block d-md-none"> <!-- Breakline sa mobile -->
                        <span class="text-muted">Student ID: {{ $student_id ?? 'N/A' }}</span>
                    </p>

                    <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-1 gap-md-2">
                        <span
                            class="badge rounded-pill {{ $has_voted ? 'bg-success-subtle text-success border-success-subtle' : 'bg-primary-light text-primary border-primary-subtle' }} border px-2 py-1"
                            style="font-size: 0.7rem;">
                            {{ $has_voted ? 'Already Voted' : 'Eligible Voter' }}
                        </span>

                        @php
                            $formattedYear = match ((string) $year_level) {
                                '1' => '1st Year',
                                '2' => '2nd Year',
                                '3' => '3rd Year',
                                '4' => '4th Year',
                                default => $year_level ?: 'Year Not Set',
                            };
                            // Panatilihin ang logic para sa formatted course kung gagamitin sa future
                            $formattedCourse = match (strtolower((string) $course)) {
                                'it' => 'Information Technology',
                                'hst' => 'Hospitality Service Technology',
                                'hrmt' => 'Hotel and Restaurant Management',
                                'ect' => 'Electronics Computer Technology',
                                default => $course ?: 'N/A',
                            };
                        @endphp

                        <span class="badge rounded-pill text-success border border-success-subtle px-2 py-1"
                            style="font-size: 0.7rem;">
                            SY : {{ $formattedYear }}
                        </span>
                    </div>
                </div>

                <!-- Button Section: Full width sa mobile (w-100), auto sa desktop -->
                <div class="col-md-auto p-3 p-md-4 text-center position-relative" style="z-index: 1060;">
                    <button class="btn btn-outline-glow btn-sm w-100 w-md-auto px-3" type="button"
                        data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="bi bi-pencil-square me-1"></i>Edit Profile
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="glass-card info-card h-100 p-4 border-0 shadow-sm">
                    <h5 class="fw-bold mb-4 text-dark">
                        <i class="bi bi-person-vcard me-2 text-primary"></i>Personal Information
                    </h5>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-emerald-light text-primary"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-hash fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Student ID</div>
                            <div class="fw-semibold text-dark">{{ $student_id ?? '---' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-primary-light text-primary"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-envelope-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Email Address</div>
                            <div class="fw-semibold text-dark">{{ $email ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-emerald-light text-primary"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-telephone-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Contact Number</div>
                            <div class="fw-semibold text-dark">{{ $phone ?? 'None' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-primary-light text-primary"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-geo-alt-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Address</div>
                            <div class="fw-semibold text-dark">{{ $address ?? 'No Address' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-warning-light text-warning"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-calendar3 fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Date of Birth</div>
                            <div class="fw-semibold text-dark">
                                {{ $birthday ? \Carbon\Carbon::parse($birthday)->format('F d, Y') : 'N/A' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Academic Information --}}
            <div class="col-lg-6">
                <div class="glass-card info-card h-100 p-4 border-0 shadow-sm">
                    <h5 class="fw-bold mb-4 text-dark">
                        <i class="bi bi-book me-2 text-primary"></i>Academic Information
                    </h5>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-emerald-light text-primary"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-mortarboard-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Course</div>
                            <div class="fw-semibold text-dark">{{ $formattedCourse }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-emerald-light text-primary"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-layers fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Year Level</div>
                            <div class="fw-semibold text-dark">{{ $formattedYear }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-primary-light text-primary"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-patch-check-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Enrollment Status</div>
                            <div class="fw-semibold text-dark">
                                <span
                                    class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">{{ $status ?: 'Enrolled' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Voting Status --}}
            <div class="col-lg-4">
                <div class="glass-card vote-status-card h-100 p-4 text-center border-0 shadow-sm">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 bg-emerald-light text-primary"
                        style="width: 60px; height: 60px;">
                        <i class="bi bi-check2-circle fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-dark">Voting Status</h5>

                    @if ($has_voted)
                        <span class="badge bg-success text-white px-3 py-2 mb-3 rounded-pill">Voted</span>
                        <p class="text-secondary mb-0 small">
                            You cast your vote on {{ \Carbon\Carbon::parse($voted_at)->format('M d, Y') }}.
                        </p>
                    @else
                        <span
                            class="badge bg-primary-light text-primary border border-primary-subtle px-3 py-2 mb-3 rounded-pill">Eligible</span>
                        <p class="text-secondary mb-0 small">You are eligible to vote in this election.</p>
                        <a href="{{ url('students/cast-vote') }}" wire:navigate
                            class="btn btn-glow btn-sm mt-4 w-100">
                            <i class="bi bi-check2-square me-1"></i>Cast Vote Now
                        </a>
                    @endif
                </div>
            </div>

            {{-- Activity Timeline --}}
            <div class="col-lg-8">
                <div class="glass-card p-4 h-100 border-0 shadow-sm">
                    <h5 class="fw-bold mb-4 text-dark">
                        <i class="bi bi-clock-history me-2 text-primary"></i>Activity Timeline
                    </h5>

                    <div class="position-relative mt-3">
                        <div class="position-absolute top-0 h-100 border-start border-light"
                            style="left: 15px; border-width: 2px !important;"></div>

                        @if ($has_voted)
                            <div class="position-relative d-flex align-items-start mb-4">
                                <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-success shadow-sm text-white"
                                    style="width: 32px; height: 32px; z-index: 2;">
                                    <i class="bi bi-check-lg"></i>
                                </div>
                                <div class="ps-3 pt-1">
                                    <div class="fw-semibold text-dark">Cast Vote Successfully</div>
                                    <small
                                        class="text-secondary">{{ \Carbon\Carbon::parse($voted_at)->format('M d, Y, h:i A') }}</small>
                                </div>
                            </div>
                        @endif

                        <div class="position-relative d-flex align-items-start mb-4">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-primary shadow-sm text-white"
                                style="width: 32px; height: 32px; z-index: 2;">
                                <i class="bi bi-box-arrow-in-right"></i>
                            </div>
                            <div class="ps-3 pt-1">
                                <div class="fw-semibold text-dark">Logged in to Voting Portal</div>
                                <small class="text-secondary">Today</small>
                            </div>
                        </div>

                        <div class="position-relative d-flex align-items-start">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-secondary shadow-sm text-white"
                                style="width: 32px; height: 32px; z-index: 2;">
                                <i class="bi bi-person-plus"></i>
                            </div>
                            <div class="ps-3 pt-1">
                                <div class="fw-semibold text-dark">Account Created</div>
                                <small class="text-secondary">Initial setup</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editProfileModal" tabindex="-1" wire:ignore.self aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-emerald-light px-4 py-2">
                        <h6 class="modal-title fw-bold" style="color: #10b981;">Account Settings</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>

                    <div class="modal-body p-4">
                        <form wire:submit.prevent="saveProfile" class="mb-4">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <label for="photoUpload" class="position-relative" style="cursor: pointer;">
                                        @if ($photo)
                                            <img src="{{ $photo->temporaryUrl() }}"
                                                class="avatar-circle border border-2 shadow-sm"
                                                style="width: 80px; height: 80px; object-fit: cover; border-color: #10b981 !important;">
                                        @elseif($profile_photo_path)
                                            <img src="{{ asset('storage/' . $profile_photo_path) }}"
                                                class="avatar-circle border shadow-sm"
                                                style="width: 80px; height: 80px; object-fit: cover;">
                                        @else
                                            <div class="avatar-circle d-flex align-items-center justify-content-center bg-emerald-light fs-4 fw-bold border"
                                                style="width: 80px; height: 80px; color: #10b981;">
                                                {{ strtoupper(substr($first_name ?: 'U', 0, 1)) }}
                                            </div>
                                        @endif
                                        <div class="position-absolute bottom-0 end-0 text-white rounded-circle d-flex align-items-center justify-content-center shadow"
                                            style="width: 25px; height: 25px; font-size: 10px; border: 2px solid white; background-color: #10b981;">
                                            <i class="bi bi-camera-fill"></i>
                                        </div>
                                    </label>
                                    <input type="file" id="photoUpload" wire:model="photo" class="d-none"
                                        accept="image/*">
                                </div>

                                <div class="col">
                                    <label class="form-label small fw-bold text-secondary mb-1">PHONE NUMBER</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-end-0"><i
                                                class="bi bi-telephone small" style="color: #10b981;"></i></span>
                                        <input type="text" class="form-control border-start-0" wire:model="phone"
                                            placeholder="09xxxxxxxxx">
                                        <button type="submit" class="btn text-white"
                                            style="background-color: #10b981; border-color: #10b981;"
                                            wire:loading.attr="disabled">
                                            <span wire:loading wire:target="saveProfile"
                                                class="spinner-border spinner-border-sm"></span>
                                            Update Profile
                                        </button>
                                    </div>
                                    @error('phone')
                                        <div class="text-danger" style="font-size: 10px;">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </form>

                        <hr class="text-secondary opacity-25">

                        <form wire:submit.prevent="updatePassword">
                            <label class="form-label small fw-bold text-secondary mb-2">CHANGE PASSWORD</label>
                            <div class="row g-2 align-items-start">
                                <div class="col-md-3">
                                    <input type="password" class="form-control form-control-sm bg-light"
                                        wire:model="current_password" placeholder="Current Password">
                                    @error('current_password')
                                        <div class="text-danger" style="font-size: 10px;">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3">
                                    <input type="password" class="form-control form-control-sm bg-light"
                                        wire:model="new_password" placeholder="New Password">
                                    @error('new_password')
                                        <div class="text-danger" style="font-size: 10px;">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-3">
                                    <input type="password" class="form-control form-control-sm bg-light"
                                        wire:model="new_password_confirmation" placeholder="Confirm Password">
                                </div>

                                <div class="col-md-3">
                                    <button type="submit" class="btn text-white btn-sm w-100 shadow-sm"
                                        style="background-color: #10b981; border-color: #10b981;"
                                        wire:loading.attr="disabled">
                                        <span wire:loading wire:target="updatePassword"
                                            class="spinner-border spinner-border-sm me-1"></span>
                                        Save Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

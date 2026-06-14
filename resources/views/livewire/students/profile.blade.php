<?php

use Carbon\Carbon;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\{Layout, Title};
use Illuminate\Validation\Rules\Password;
use App\Http\Requests\Students\UpdateProfileRequest;
use App\Http\Requests\Students\UpdatePasswordRequest;
use App\Traits\{ChecksMaintenance, AuthenticatesLogout};
use Illuminate\Support\Facades\Auth;
use App\Services\Student\ProfileService;

/**
 * Student Profile component.
 *
 * Displays personal and academic information, voting status,
 * activity timeline, and provides an edit modal for updating
 * profile photo, phone number, and account password.
 */
new #[Layout('layouts.app')] #[Title('My Profile')] class extends Component {
    use ChecksMaintenance, AuthenticatesLogout, WithFileUploads;

    public $name = '';
    public $email = '';
    public $student;
    public $user_id;
    public $student_id = '';
    public $first_name = '';
    public $middle_name = '';
    public $last_name = '';
    public $suffix = '';
    public $course = '';
    public $status = '';
    public $year_level = '';
    public $block_section = '';
    public $phone = '';
    public $address = '';
    public $birthday = '';
    public $gender = '';
    public $photo = null;
    public $profile_photo_path = null;
    public $has_voted = false;
    public $voted_at = null;
    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';
    public bool $isVotingOpen = false;

    private ProfileService $profileService;

    /**
     * Inject the ProfileService dependency.
     *
     * @param  ProfileService  $profileService
     */
    public function boot(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    /**
     * Load student profile data from the service into component properties.
     */
    public function mount()
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $data = $this->profileService->getProfileData($user);
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Update the student's profile (phone, photo) with validation.
     */
    public function saveProfile()
    {
        $request = app(UpdateProfileRequest::class);
        $validated = $this->validate($request->rules());
        $user = Auth::user();

        $result = $this->profileService->updateProfile($user, $validated, $this->photo);

        if (isset($result['error'])) {
            $this->dispatch('swal', [
                'title' => 'Update Failed',
                'text' => $result['error'],
                'icon' => 'error',
            ]);
            return;
        }

        if (isset($result['profile_photo_path'])) {
            $this->profile_photo_path = $result['profile_photo_path'];
        }

        $this->reset('photo');

        $this->dispatch('swal', [
            'title' => 'Profile Updated',
            'text' => 'Your personal information has been saved successfully.',
            'icon' => 'success',
            'timer' => 3000,
            'showConfirmButton' => false,
        ]);
        $this->dispatch('close-modal');
    }

    /**
     * Change the student's password with current password confirmation.
     */
    public function updatePassword()
    {
        $request = app(UpdatePasswordRequest::class);
        $validated = $this->validate($request->rules());

        $this->profileService->updatePassword(Auth::user(), $validated['new_password']);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);

        $this->dispatch('swal', [
            'title' => 'Password Changed',
            'text' => 'Your account security has been updated.',
            'icon' => 'success',
            'timer' => 3000,
        ]);
    }
}; ?>

{{-- Mobile header bar shown only on small screens --}}
<div>
    <div
        class="d-lg-none d-flex align-items-center justify-content-start p-2 px-4 bg-white shadow-sm gap-2 border-bottom">
        <img src="{{ asset('images/logo.png') }}" alt="Logo" style="height: 45px; width: 45px; object-fit: contain;">

        <h4 class="mb-0 text-primary" style="font-size: 0.95rem; font-weight: 600; color: #1e293b;">
            Top Link Global College, Inc.
        </h4>
    </div>

    {{-- Student sidebar navigation --}}
    @include('layouts.partials.student-sidebar')

    <main class="main-content" style="font-size: clamp(13px, 2vw + 8px, 16px);">
        {{-- Top bar with page title --}}
        <div class="topbar" wire:key="persistent-topbar-header">
            <div>
                <h2 class="text-dark">My <span class="text-primary">Profile</span></h2>
                <p class="text-secondary mb-0 small">View your student information and voting status</p>
            </div>
        </div>

        {{-- Profile header card with photo, name, student ID, and voting/year badges --}}
        <div class="glass-card profile-header m-2 m-md-4 fade-in-up delay-1 border-0 shadow-sm overflow-hidden">
            <div class="row align-items-center g-0">
                <div class="col-md-auto text-center text-md-start p-2 p-md-4">
                    <div class="d-inline-flex flex-column align-items-center">
                        <label class="position-relative d-inline-block cursor-pointer"
                            style="width: 80px; height: 80px;" data-bs-toggle="modal"
                            data-bs-target="#editProfileModal">

                            <div class="rounded-circle overflow-hidden border shadow-sm w-100 h-100">
                                @if ($photo)
                                    <img src="{{ $photo->temporaryUrl() }}" class="w-100 h-100 object-fit-cover">
                                @elseif($profile_photo_path)
                                    <img src="{{ asset('storage/' . $profile_photo_path) }}"
                                        class="w-100 h-100 object-fit-cover">
                                @else
                                    <div
                                        class="w-100 h-100 d-flex align-items-center justify-center bg-emerald-light fw-bold text-primary">
                                        {{ strtoupper(substr($first_name ?: 'U', 0, 1) . substr($last_name ?: 'P', 0, 1)) }}
                                    </div>
                                @endif
                            </div>
                        </label>

                        <div class="mt-2 text-muted fw-bold"
                            style="font-size: 10px; cursor: pointer; text-transform: uppercase;" data-bs-toggle="modal"
                            data-bs-target="#editProfileModal">
                            Tap to change profile
                        </div>
                    </div>
                </div>

                <div class="col-md px-3 px-md-0 py-2 py-md-4 mb-3 mb-md-0 text-center text-md-start">
                    <h4 class="fw-bold mb-1 text-dark text-truncate text-base ">
                        {{ $first_name ?: 'Student' }}
                        {{ $middle_name ? substr($middle_name, 0, 1) . '.' : '' }}
                        {{ $last_name ?: '' }}
                        {{ $suffix ?? '' }}
                    </h4>
                    <p class="text-secondary mb-2 fw-semibold small">
                        <i class="bi bi-mortarboard-fill me-1 text-primary"></i>
                        <span class="d-inline-block">{{ $course ?: 'N/A' }} -
                            {{ $year_level ?: 'N/A' }}{{ $block_section ?: 'N/A' }}</span>
                        <span class="mx-1 d-none d-md-inline">|</span>
                        <br class="d-block d-md-none">
                        <span class="text-muted">Student ID: {{ $student_id ?? 'N/A' }}</span>
                    </p>

                    {{-- Voting status and year level badges --}}
                    <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-1 gap-md-2">
                        <span
                            class="badge rounded-pill {{ $has_voted ? 'bg-success-subtle text-success border-success-subtle' : 'bg-primary-light text-primary border-success-subtle' }} border px-2 py-1"
                            style="font-size: 0.7rem;">
                            {{ $has_voted ? 'Already Voted' : 'Eligible Voter' }}
                        </span>

                        @php
                            $formattedYear = match ((string) $year_level) {
                                '1' => '1st Year',
                                '2' => '2nd Year',
                                '3' => '3rd Year',
                                default => $year_level ?: 'Year Not Set',
                            };
                            $formattedCourse = match (strtolower((string) $course)) {
                                'it' => 'Information Technology',
                                'hst' => 'Hospitality Service Technology',
                                'hrmt' => 'Hotel and Restaurant Management',
                                'ect' => 'Electronics Computer Technology',
                                default => $course ?: 'N/A',
                            };
                        @endphp

                        <span class="badge rounded-pill text-primary border border-success-subtle px-2 py-1"
                            style="font-size: 0.7rem;">
                            SY : {{ $formattedYear }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Personal and Academic information columns --}}
        <div class="row g-4">
            {{-- Personal Information card --}}
            <div class="col-lg-6">
                <div class="glass-card info-card h-100 p-4 border-0 shadow-sm">
                    <h5 class="fw-bold mb-4 text-dark">
                        <i class="bi bi-person-vcard me-2 text-primary"></i>Personal Information
                    </h5>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-emerald-light text-primary"
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
                            <i class="bi bi-gender-ambiguous fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Gender</div>
                            <div class="fw-semibold text-dark">{{ $gender ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-emerald-light text-primary"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-telephone-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Contact Number</div>
                            <div class="fw-semibold text-dark">{{ $phone ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-emerald-light text-primary"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-geo-alt-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Address</div>
                            <div class="fw-semibold text-dark">{{ $address ?? 'No Address' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-emerald-light text-primary"
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

            {{-- Academic Information card --}}
            <div class="col-lg-6">
                <div class="glass-card info-card h-100 p-4 border-0 shadow-sm">
                    <h5 class="fw-bold mb-4 text-dark">
                        <i class="bi bi-book me-2 text-primary"></i>Academic Information
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

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-emerald-light text-primary"
                            style="width: 40px; height: 40px;">
                            <i class="bi bi-person fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-secondary">Section</div>
                            <div class="fw-semibold text-dark">{{ $year_level }}{{ $block_section }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded bg-emerald-light text-primary"
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

            {{-- Voting Status card --}}
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
                    @elseif ($isVotingOpen)
                        <span
                            class="badge bg-primary-light text-primary border border-primary-subtle px-3 py-2 mb-3 rounded-pill">Eligible</span>
                        <p class="text-secondary mb-0 small">You are eligible to vote in this election.</p>
                        <a href="{{ url('students/cast-vote') }}" wire:navigate class="btn btn-glow w-100 py-1">
                            <i class="bi bi-check2-square me-1"></i>Cast Vote Now
                        </a>
                    @else
                        <span class="badge bg-dark-subtle text-muted border px-3 py-2 mb-3 rounded-pill">
                            <i class="bi bi-lock-fill me-1"></i>Locked
                        </span>
                        <p class="text-secondary mb-0 small">Voting is currently closed or has not yet started.</p>
                        <button class="btn btn-secondary btn-sm mt-4 mt-md-2 w-100 w-md-50" disabled>
                            Voting Unavailable
                        </button>
                    @endif
                </div>
            </div>

            {{-- Activity Timeline card --}}
            <div class="col-lg-8">
                <div class="glass-card p-4 h-100 border-0 shadow-sm">
                    <h5 class="fw-bold mb-4 text-dark d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-clock-history me-2 text-primary"></i>Activity Timeline</span>
                    </h5>

                    <div class="position-relative mt-3 ps-2">
                        <div class="position-absolute border-start border-light-subtle"
                            style="left: 22px; top: 15px; bottom: 15px; border-width: 2px !important; border-style: dashed !important;">
                        </div>

                        @if ($has_voted)
                            {{-- Vote submission timeline entry --}}
                            <div class="position-relative d-flex align-items-start mb-4">
                                <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-success shadow-sm text-white"
                                    style="width: 32px; height: 32px; z-index: 2;">
                                    <i class="bi bi-check-lg lh-1 fs-6"></i>
                                </div>
                                <div class="ps-3 pt-1">
                                    <div class="fw-semibold text-dark d-flex align-items-center">
                                        Cast Vote Successfully
                                        <span class="badge bg-success-subtle text-success ms-2 font-monospace"
                                            style="font-size: 0.65rem;">LATEST</span>
                                    </div>
                                    <small
                                        class="text-secondary">{{ Carbon::parse($voted_at)->format('M d, Y, h:i A') }}</small>
                                </div>
                            </div>
                        @endif

                        {{-- Login timeline entry --}}
                        <div class="position-relative d-flex align-items-start mb-4">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-primary shadow-sm text-white"
                                style="width: 32px; height: 32px; z-index: 2;">
                                <i class="bi bi-box-arrow-in-right"></i>
                            </div>
                            <div class="ps-3 pt-1">
                                <div class="fw-semibold text-dark">
                                    Logged in to Voting Portal
                                    @if (!$has_voted)
                                        <span class="badge bg-primary-subtle text-primary ms-2 font-monospace"
                                            style="font-size: 0.65rem;">LATEST</span>
                                    @endif
                                </div>
                                <small class="text-secondary">Today, {{ now()->format('h:i A') }}</small>
                            </div>
                        </div>

                        {{-- Account verification timeline entry --}}
                        <div class="position-relative d-flex align-items-start mb-12 mb-md-0">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-secondary shadow-sm text-white"
                                style="width: 32px; height: 32px; z-index: 2;">
                                <i class="bi bi-person-plus"></i>
                            </div>
                            <div class="ps-3 pt-1">
                                <div class="fw-semibold text-dark">Account Verified</div>
                                <small class="text-secondary">Initial setup completed</small>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Edit Profile modal with photo upload, phone update, and password change --}}
    <div class="modal fade" id="editProfileModal" tabindex="-1" wire:ignore.self aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-emerald-light px-4 py-2">
                    <h6 class="modal-title fw-bold" style="color: #10b981;">Account Settings</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    {{-- Profile photo and phone update form --}}
                    <form wire:submit.prevent="saveProfile" class="mb-4">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <label for="photoUpload" class="position-relative" style="cursor: pointer;">
                                    <div wire:loading wire:target="photo"
                                        class="position-absolute top-50 start-50 translate-middle"
                                        style="z-index: 10;">
                                        <div class="spinner-border spinner-border-sm text-emerald" role="status">
                                        </div>
                                    </div>
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
                                <label class="form-label small fw-bold text-secondary mb-1"
                                    style="font-size: 0.8rem;">PHONE NUMBER</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="bi bi-telephone small" style="color: #10b981;"></i></span>
                                    <input type="text" class="form-control border-start-0" wire:model="phone"
                                        placeholder="09xxxxxxxxx" maxlength="11">
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

                    {{-- Password change form --}}
                    <form wire:submit.prevent="updatePassword">
                        <label class="form-label small fw-bold text-secondary mb-2" style="font-size: 0.8rem;">CHANGE
                            PASSWORD</label>
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
</div>

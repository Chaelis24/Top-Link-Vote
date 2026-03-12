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

    // Student Profile State
    public $user_id;
    public $student_id = '';
    public $first_name = '';
    public $middle_name = '';
    public $last_name = '';
    public $suffix = '';
    public $course = '';
    public $department = ''; // Added for new UI
    public $gpa = ''; // Added for new UI
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
            $this->department = $profile->department ?? '';
            $this->gpa = $profile->gpa ?? '';
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

        $this->dispatch('notify', message: 'Profile updated successfully!', type: 'success');
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
        $this->dispatch('notify', message: 'Password changed successfully!', type: 'success');
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
        return $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>

    @include('layouts.partials.student-sidebar')

    <main class="main-content">
        <div class="topbar" wire:key="persistent-topbar-header">
            <div>
                <h2>My <span>Profile</span></h2>
                <p class="text-white-50 mb-0">View your student information and voting status</p>
            </div>
            <a href="/students/profile" wire:navigate class="text-decoration-none">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-circle overflow-hidden">
                        @if ($photo?->temporaryUrl())
                            <img src="{{ $photo?->temporaryUrl() }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @elseif($profile_photo_path)
                            <img src="{{ asset('storage/' . $profile_photo_path) }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <i class="bi bi-person-fill text-white"></i>
                        @endif
                    </div>
                </div>
            </a>
        </div>

        <div class="glass-card profile-header mb-4 fade-in-up delay-1">
            <div class="row align-items-center">
                <div class="col-md-auto text-center text-md-start mb-3 mb-md-0">
                    <div class="position-relative d-inline-block">
                        @if ($photo?->temporaryUrl())
                            <img src="{{ $photo?->temporaryUrl() }}" class="profile-avatar-lg">
                        @elseif($profile_photo_path)
                            <img src="{{ asset('storage/' . $profile_photo_path) }}" class="profile-avatar-lg">
                        @else
                            <div
                                class="profile-avatar-lg d-flex align-items-center justify-content-center bg-accent fs-2 fw-bold text-white">
                                {{ strtoupper(substr($first_name ?: 'U', 0, 1) . substr($last_name ?: 'P', 0, 1)) }}
                            </div>
                        @endif
                        <span class="profile-badge verified"><i class="bi bi-check-lg"></i></span>
                    </div>
                </div>
                <div class="col-md">
                    <h3 class="fw-bold mb-1 text-white">{{ $name ?? 'Guest User' }}</h3>
                    <p class="text-white-50 mb-2">
                        <i class="bi bi-mortarboard-fill me-1 text-accent"></i>
                        {{ $course ?: 'N/A' }} | Student ID: {{ $student_id ?? 'N/A' }}
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <span
                            class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success-subtle px-3">
                            {{ $has_voted ? 'Already Voted' : 'Eligible Voter' }}
                        </span>
                        <span
                            class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary-subtle px-3">
                            S.Y. {{ $year_level ?: 'N/A' }}
                        </span>
                    </div>
                </div>
                <div class="col-md-auto mt-3 mt-md-0" style="position: relative; z-index: 10;">
                    <button class="btn btn-outline-glow btn-sm" data-bs-toggle="modal"
                        data-bs-target="#editProfileModal">
                        <i class="bi bi-pencil-square me-1"></i>Edit Profile
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-4">
            {{-- Personal Information --}}
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="200">
                <div class="glass-card info-card h-100 p-4">
                    <h5 class="fw-bold mb-4">
                        <i class="bi bi-person-vcard me-2" style="color: var(--accent);"></i>
                        Personal Information
                    </h5>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded"
                            style="width: 40px; height: 40px; background: rgba(56, 142, 60, 0.15); color: var(--accent);">
                            <i class="bi bi-hash fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-white-50">Student ID</div>
                            <div class="fw-semibold text-white">{{ $student_id ?? '---' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded"
                            style="width: 40px; height: 40px; background: rgba(103, 58, 183, 0.15); color: var(--purple);">
                            <i class="bi bi-envelope-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-white-50">Email Address</div>
                            <div class="fw-semibold text-white">{{ $email ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded"
                            style="width: 40px; height: 40px; background: rgba(56, 142, 60, 0.15); color: var(--accent);">
                            <i class="bi bi-telephone-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-white-50">Contact Number</div>
                            <div class="fw-semibold text-white">{{ $phone ?? 'None' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded"
                            style="width: 40px; height: 40px; background: rgba(103, 58, 183, 0.15); color: var(--purple);">
                            <i class="bi bi-geo-alt-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-white-50">Address</div>
                            <div class="fw-semibold text-white">{{ $address ?? 'No Address' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded"
                            style="width: 40px; height: 40px; background: rgba(253, 203, 110, 0.15); color: var(--warning);">
                            <i class="bi bi-calendar3 fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-white-50">Date of Birth</div>
                            <div class="fw-semibold text-white">
                                {{ $birthday ?: 'N/A' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Academic Information --}}
            <div class="col-lg-6" data-aos="fade-up" data-aos-delay="300">
                <div class="glass-card info-card h-100 p-4">
                    <h5 class="fw-bold mb-4">
                        <i class="bi bi-book me-2" style="color: var(--purple);"></i>
                        Academic Information
                    </h5>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded"
                            style="width: 40px; height: 40px; background: rgba(56, 142, 60, 0.15); color: var(--accent);">
                            <i class="bi bi-mortarboard-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-white-50">Course</div>
                            <div class="fw-semibold text-white">{{ $course ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded"
                            style="width: 40px; height: 40px; background: rgba(103, 58, 183, 0.15); color: var(--purple);">
                            <i class="bi bi-building fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-white-50">Department</div>
                            <div class="fw-semibold text-white">{{ $department ?? 'N/A' }}
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded"
                            style="width: 40px; height: 40px; background: rgba(56, 142, 60, 0.15); color: var(--accent);">
                            <i class="bi bi-layers fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-white-50">Year Level</div>
                            <div class="fw-semibold text-white">{{ $year_level ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded"
                            style="width: 40px; height: 40px; background: rgba(253, 203, 110, 0.15); color: var(--warning);">
                            <i class="bi bi-award-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-white-50">GPA</div>
                            <div class="fw-semibold text-white">{{ $gpa ?? '0.00' }}</div>
                        </div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="info-icon d-flex align-items-center justify-content-center rounded"
                            style="width: 40px; height: 40px; background: rgba(103, 58, 183, 0.15); color: var(--purple);">
                            <i class="bi bi-patch-check-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-white-50">Enrollment Status</div>
                            <div class="fw-semibold text-white">
                                <span
                                    class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1">{{ $status ?: 'Enrolled' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Voting Status --}}
            <div class="col-lg-4" data-aos="fade-up" data-aos-delay="400">
                <div class="glass-card vote-status-card h-100 p-4 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3"
                        style="width: 60px; height: 60px; background: rgba(56, 142, 60, 0.15); color: var(--accent);">
                        <i class="bi bi-check2-circle fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-2 text-white">Voting Status</h5>

                    @if ($has_voted)
                        <span class="badge bg-success text-white px-3 py-2 mb-3 rounded-pill">Voted</span>
                        <p class="text-white-50 mb-0 small">
                            You have successfully cast your vote on
                            {{ $voted_at ? \Carbon\Carbon::parse($voted_at)->format('M d, Y') : 'the election date' }}.
                        </p>
                    @else
                        <span
                            class="badge bg-primary bg-opacity-25 text-primary border border-primary-subtle px-3 py-2 mb-3 rounded-pill">Eligible</span>
                        <p class="text-white-50 mb-0 small">
                            You are eligible to vote in the current Student Council Election.
                        </p>
                    @endif

                    <hr class="border-white-10 my-3">
                    <div class="text-start ps-2">
                        <small class="text-white-50 d-block mb-1">
                            <strong style="color: var(--accent);">Has Voted:</strong> {{ $has_voted ? 'Yes' : 'No' }}
                        </small>
                        <small class="text-white-50 d-block mb-1">
                            <strong style="color: var(--purple);">Election:</strong> S.Y.
                            {{ $year_level ?: '2025-2026' }}
                        </small>
                    </div>

                    @if (!$has_voted)
                        <a href="{{ url('students/cast-vote') }}" wire:navigate
                            class="btn btn-glow btn-sm mt-4 w-100">
                            <i class="bi bi-check2-square me-1"></i>Cast Your Vote Now
                        </a>
                    @endif
                </div>
            </div>

            {{-- Activity Timeline --}}
            <div class="col-lg-8" data-aos="fade-up" data-aos-delay="500">
                <div class="glass-card p-4 h-100">
                    <h5 class="fw-bold mb-4 text-white">
                        <i class="bi bi-clock-history me-2" style="color: var(--accent);"></i>
                        Activity Timeline
                    </h5>

                    <div class="position-relative mt-3">
                        <div class="position-absolute top-0 h-100 border-start border-white-10" style="left: 15px;">
                        </div>

                        @if ($has_voted)
                            <div class="position-relative d-flex align-items-start mb-4">
                                <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle bg-success shadow"
                                    style="width: 32px; height: 32px; z-index: 2;">
                                    <i class="bi bi-check-lg text-white" style="font-size: 1.1rem;"></i>
                                </div>
                                <div class="ps-3 pt-1">
                                    <div class="fw-semibold text-white" style="line-height: 1.2;">Cast Vote
                                        Successfully</div>
                                    <small
                                        class="text-white-50">{{ $voted_at ? \Carbon\Carbon::parse($voted_at)->format('M d, Y, h:i A') : 'Recently' }}</small>
                                </div>
                            </div>
                        @endif

                        <div class="position-relative d-flex align-items-start mb-4">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle shadow"
                                style="width: 32px; height: 32px; background: var(--accent); z-index: 2;">
                                <i class="bi bi-box-arrow-in-right text-white" style="font-size: 1.1rem;"></i>
                            </div>
                            <div class="ps-3 pt-1">
                                <div class="fw-semibold text-white" style="line-height: 1.2;">Logged in to Voting
                                    Portal</div>
                                <small class="text-white-50">Today</small>
                            </div>
                        </div>

                        <div class="position-relative d-flex align-items-start mb-4">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle shadow"
                                style="width: 32px; height: 32px; background: var(--warning); z-index: 2;">
                                <i class="bi bi-person-check text-white" style="font-size: 1.1rem;"></i>
                            </div>
                            <div class="ps-3 pt-1">
                                <div class="fw-semibold text-white" style="line-height: 1.2;">Account Verified for
                                    Voting</div>
                                <small class="text-white-50">System generated</small>
                            </div>
                        </div>

                        <div class="position-relative d-flex align-items-start">
                            <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle shadow"
                                style="width: 32px; height: 32px; background: var(--purple); z-index: 2;">
                                <i class="bi bi-person-plus text-white" style="font-size: 1.1rem;"></i>
                            </div>
                            <div class="ps-3 pt-1">
                                <div class="fw-semibold text-white" style="line-height: 1.2;">Account Created</div>
                                <small class="text-white-50">Initial setup</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    {{-- Edit Profile Modal --}}
    <div class="modal fade" id="editProfileModal" tabindex="-1" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-white-10 shadow-lg" style="background: #1a1a2e; border-radius: 20px;">
                <div class="modal-header border-white-10">
                    <h5 class="modal-title fw-bold text-white">Update Information</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form wire:submit="saveProfile">
                    <div class="modal-body p-4">
                        <div class="col-12 text-center mb-4">
                            <label for="photoUpload" style="cursor: pointer;" class="position-relative">
                                @if ($photo?->temporaryUrl())
                                    <img src="{{ $photo?->temporaryUrl() }}"
                                        style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent);">
                                @elseif($profile_photo_path)
                                    <img src="{{ asset('storage/' . $profile_photo_path) }}"
                                        style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent);">
                                @else
                                    <div
                                        style="width: 120px; height: 120px; border-radius: 50%; background: #252545; display: flex; align-items: center; justify-content: center; border: 2px dashed rgba(255,255,255,0.2);">
                                        <i class="bi bi-camera fs-1 text-white-50"></i>
                                    </div>
                                @endif
                            </label>
                            <input type="file" id="photoUpload" wire:model="photo" class="d-none"
                                accept="image/*">
                            @error('photo')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small text-white-50">Email</label>
                                <input type="email" wire:model="email"
                                    class="form-control bg-dark border-white-10 text-white">
                                @error('email')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-white-50">Phone</label>
                                <input type="text" wire:model="phone"
                                    class="form-control bg-dark border-white-10 text-white">
                                @error('phone')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>

                        <hr class="my-4 border-white-10">
                        <h6 class="text-accent small fw-bold mb-3">Security</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small text-white-50">Current Password</label>
                                <input type="password" wire:model="current_password"
                                    class="form-control bg-dark border-white-10 text-white"
                                    placeholder="Current Password">
                                @error('current_password')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-white-50">New Password</label>
                                <input type="password" wire:model="new_password"
                                    class="form-control bg-dark border-white-10 text-white"
                                    placeholder="New Password">
                                @error('new_password')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-white-50">Confirm Password</label>
                                <input type="password" wire:model="new_password_confirmation"
                                    class="form-control bg-dark border-white-10 text-white"
                                    placeholder="Confirm Password">
                            </div>
                            <div class="col-12 text-end">
                                <button type="button" wire:click="updatePassword"
                                    class="btn btn-outline-glow btn-sm">Update Password</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-white-10">
                        <button type="button" class="btn btn-outline-glow btn-sm"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-glow btn-sm">Save Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

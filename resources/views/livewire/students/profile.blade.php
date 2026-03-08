<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;

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
        $user = Auth::user()->load('student');
        $profile = $user->student;

        $this->name = $user->name;
        $this->email = $user->email;

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
            'first_name' => 'required|min:2',
            'last_name' => 'required|min:2',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'required|numeric',
            'address' => 'required',
            'birthday' => 'required|date',
            'gender' => 'required|in:Male,Female,Other',
            'photo' => 'nullable|image|max:2048',
        ]);

        $user->update([
            'name' => trim($this->first_name . ' ' . $this->last_name . ' ' . $this->suffix),
            'email' => $this->email,
        ]);

        if ($user->student) {
            $data = [
                'first_name' => $this->first_name,
                'middle_name' => $this->middle_name,
                'last_name' => $this->last_name,
                'suffix' => $this->suffix,
                'phone' => $this->phone,
                'address' => $this->address,
                'birthday' => $this->birthday,
                'gender' => $this->gender,
            ];

            if ($this->photo && !is_string($this->photo)) {
                $data['photo'] = $this->photo->store('profile-photos', 'public');
                $this->profile_photo_path = $data['photo'];
            }

            $user->student->update($data);
        }

        $this->name = $user->name;
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

        Auth::user()->update([
            'password' => Hash::make($this->new_password),
        ]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->dispatch('notify', message: 'Password changed successfully!', type: 'success');
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
                        @if ($photo ?? '')
                            <img src="{{ $photo->temporaryUrl() }}"
                                style="width: 100%; height: 100%; object-fit: cover;">
                        @elseif($profile_photo_path ?? '')
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
                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}" class="profile-avatar-lg">
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
                    <h3 class="fw-bold mb-1 text-white">{{ $name }}</h3>
                    <p class="text-white-50 mb-2">
                        <i class="bi bi-mortarboard-fill me-1 text-accent"></i>
                        {{ $course ?? 'N/A' }} | Student ID: {{ $student_id }}
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <span
                            class="badge rounded-pill bg-success bg-opacity-10 text-success border border-success-subtle px-3">
                            {{ $has_voted ? 'Already Voted' : 'Eligible Voter' }}
                        </span>
                        <span
                            class="badge rounded-pill bg-primary bg-opacity-10 text-primary border border-primary-subtle px-3">
                            S.Y. {{ $year_level ?? 'N/A' }}
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
            <div class="col-lg-6 fade-in-up delay-2">
                <div class="glass-card info-card h-100 p-4">
                    <h5 class="fw-bold mb-4 text-white"><i class="bi bi-person-vcard me-2 text-accent"></i>Personal Info
                    </h5>
                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon-box"><i class="bi bi-hash text-accent"></i></div>
                        <div>
                            <small class="text-white-50 d-block">Student ID</small>
                            <span class="text-white fw-semibold">{{ $student_id ?: 'Not Set' }}</span>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon-box"><i class="bi bi-envelope text-purple"></i></div>
                        <div>
                            <small class="text-white-50 d-block">Email Address</small>
                            <span class="text-white fw-semibold">{{ $email }}</span>
                        </div>
                    </div>
                    <div class="d-flex gap-3 mb-4">
                        <div class="info-icon-box"><i class="bi bi-telephone text-accent"></i></div>
                        <div>
                            <small class="text-white-50 d-block">Contact</small>
                            <span class="text-white fw-semibold">{{ $phone ?: 'No Contact No.' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 fade-in-up delay-3">
                <div class="glass-card p-4 h-100">
                    <h5 class="fw-bold mb-4 text-white"><i class="bi bi-clock-history me-2 text-warning"></i>Activity
                    </h5>
                    <div class="timeline">
                        <div class="d-flex gap-3 mb-3 pb-3 border-bottom border-white-5">
                            <div class="timeline-dot-sm {{ $has_voted ? 'bg-success' : 'bg-warning' }}"></div>
                            <span class="text-white-50 small">
                                {{ $has_voted ? 'Voted successfully at ' . $voted_at?->timezone('Asia/Manila')->format('F d, Y - h:i A') : 'You have not cast your vote yet.' }}
                            </span>
                        </div>
                    </div>
                    @if (!$has_voted)
                        <a href="{{ url('students/cast-vote') }}" wire:navigate class="btn btn-glow w-100 mt-3">Go to
                            Voting Page</a>
                    @endif
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
                                @if ($photo)
                                    <img src="{{ $photo->temporaryUrl() }}"
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
                            <input type="file" id="photoUpload" wire:model="photo" class="d-none" accept="image/*">
                            @error('photo')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small text-white-50">First Name</label>
                                <input type="text" wire:model="first_name"
                                    class="form-control bg-dark border-white-10 text-white">
                                @error('first_name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-white-50">Middle Name</label>
                                <input type="text" wire:model="middle_name"
                                    class="form-control bg-dark border-white-10 text-white">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-white-50">Last Name</label>
                                <input type="text" wire:model="last_name"
                                    class="form-control bg-dark border-white-10 text-white">
                                @error('last_name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
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
                            <div class="col-12">
                                <label class="form-label small text-white-50">Address</label>
                                <input type="text" wire:model="address"
                                    class="form-control bg-dark border-white-10 text-white">
                                @error('address')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-white-50">Birthday</label>
                                <input type="date" wire:model="birthday"
                                    class="form-control bg-dark border-white-10 text-white">
                                @error('birthday')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-white-50">Gender</label>
                                <select wire:model="gender" class="form-select bg-dark border-white-10 text-white">
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                                @error('gender')
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

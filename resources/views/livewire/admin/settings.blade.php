<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

new #[Layout('layouts.app')] #[Title('Admin Settings')] class extends Component {
    // Profile Props
    public string $name = '';
    public string $email = '';

    // Password Props
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount()
    {
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
    }

    public function updateProfile()
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . auth()->id()],
        ]);

        auth()->user()->update($validated);

        session()->flash('success', 'Profile updated successfully.');
    }

    public function updatePassword()
    {
        $validated = $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        auth()
            ->user()
            ->update([
                'password' => Hash::make($validated['password']),
            ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        session()->flash('success', 'Password changed successfully.');
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();

        return $this->redirect('/', navigate: true);
    }
}; ?>

<div x-data="{
    showCurrent: false,
    showNew: false,
    showConfirm: false,
    strength: 0,
    checkStrength(pw) {
        let s = 0;
        if (pw.length >= 8) s++;
        if (/[A-Z]/.test(pw)) s++;
        if (/[0-9]/.test(pw)) s++;
        if (/[^A-Za-z0-9]/.test(pw)) s++;
        this.strength = s;
    }
}">

    @include('layouts.partials.admin-sidebar')

    <div class="main-content">
        <div class="topbar">
            <div>
                <h2><span>Settings</span></h2>
                <p class="text-secondary mb-0" style="font-size: 0.85rem;">Manage your account and security preferences
                </p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-md-block">
                    <div class="fw-semibold" style="font-size: 0.9rem;">{{ auth()->user()->name }}</div>
                    <small class="text-secondary">Administrator</small>
                </div>
                <div
                    style="width: 42px; height: 42px; border-radius: 12px; background: linear-gradient(135deg, var(--accent), var(--purple)); display: flex; align-items: center; justify-content: center; font-weight: 700;">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
            </div>
        </div>

        {{-- Alert Messages --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show glass" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-4">
            {{-- Profile Information Card --}}
            <div class="col-lg-6 fade-in-up delay-1">
                <div class="glass-card p-0 h-100">
                    <div class="p-4 border-bottom" style="border-color: var(--glass-border) !important;">
                        <h5 class="mb-0 fw-bold">Profile Information</h5>
                    </div>
                    <div class="p-4">
                        <form wire:submit="updateProfile">
                            <div class="text-center mb-4">
                                <div class="mx-auto"
                                    style="width: 80px; height: 80px; border-radius: 20px; background: linear-gradient(135deg, var(--accent), var(--purple)); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800;">
                                    {{ strtoupper(substr($name, 0, 2)) }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label admin-label text-white">Full Name</label>
                                <input type="text" wire:model="name"
                                    class="form-control admin-input @error('name') is-invalid @enderror">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="form-label admin-label text-white">Email Address</label>
                                <input type="email" wire:model="email"
                                    class="form-control admin-input @error('email') is-invalid @enderror">
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-glow w-100">
                                <span wire:loading.remove wire:target="updateProfile">Update Profile</span>
                                <span wire:loading wire:target="updateProfile">Saving...</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Change Password Card --}}
            <div class="col-lg-6 fade-in-up delay-2">
                <div class="glass-card p-0 h-100">
                    <div class="p-4 border-bottom" style="border-color: var(--glass-border) !important;">
                        <h5 class="mb-0 fw-bold">Change Password</h5>
                    </div>
                    <div class="p-4">
                        <form wire:submit="updatePassword">
                            {{-- Current Password --}}
                            <div class="mb-3">
                                <label class="form-label admin-label text-white">Current Password</label>
                                <div class="position-relative">
                                    <input :type="showCurrent ? 'text' : 'password'" wire:model="current_password"
                                        class="form-control admin-input @error('current_password') is-invalid @enderror">
                                    <button type="button" @click="showCurrent = !showCurrent"
                                        class="btn position-absolute top-50 end-0 translate-middle-y border-0 bg-transparent">
                                        <i class="bi" :class="showCurrent ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                                @error('current_password')
                                    <div class="text-danger mt-1 small">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- New Password --}}
                            <div class="mb-3">
                                <label class="form-label admin-label text-white">New Password</label>
                                <div class="position-relative">
                                    <input :type="showNew ? 'text' : 'password'" wire:model.live="password"
                                        @input="checkStrength($el.value)"
                                        class="form-control admin-input @error('password') is-invalid @enderror">
                                    <button type="button" @click="showNew = !showNew"
                                        class="btn position-absolute top-50 end-0 translate-middle-y border-0 bg-transparent">
                                        <i class="bi" :class="showNew ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                                {{-- Password Strength UI --}}
                                <div class="mt-2">
                                    <div class="d-flex gap-1 mb-1">
                                        <template x-for="i in 4">
                                            <div class="flex-fill rounded" style="height: 3px;"
                                                :style="i <= strength ? {
                                                    background: ['#ff6b6b', '#fdcb6e', '#74b9ff',
                                                        '#00b894'
                                                    ][strength - 1]
                                                } : { background: 'rgba(255,255,255,0.1)' }">
                                            </div>
                                        </template>
                                    </div>
                                </div>
                                @error('password')
                                    <div class="text-danger mt-1 small">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Confirm Password --}}
                            <div class="mb-4">
                                <label class="form-label admin-label text-white">Confirm New Password</label>
                                <div class="position-relative">
                                    <input :type="showConfirm ? 'text' : 'password'" wire:model="password_confirmation"
                                        class="form-control admin-input">
                                    <button type="button" @click="showConfirm = !showConfirm"
                                        class="btn position-absolute top-50 end-0 translate-middle-y border-0 bg-transparent">
                                        <i class="bi" :class="showConfirm ? 'bi-eye-slash' : 'bi-eye'"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-glow w-100">
                                Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

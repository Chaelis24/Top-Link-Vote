<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

new #[Layout('layouts.admin')] #[Title('Settings')] class extends Component {
    public string $name = '';
    public string $email = '';

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

        $this->dispatch('swal', [
            'title' => 'Admin Profile Updated',
            'text' => 'Your information is now up to date.',
            'icon' => 'success',
        ]);
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

        $this->dispatch('swal', [
            'title' => 'Security Updated',
            'text' => 'Admin password changed successfully.',
            'icon' => 'success',
        ]);
    }

    public function logout()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
        return redirect()->route('admin.login');
    }
}; ?>
<div wire:poll.15s x-data="{
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
            <div class="topbar-info">
                <h2 class="fw-bold text-primary">Account <span class="text-accent">Settings</span></h2>
                <p class="text-muted mb-0" style="font-size: 0.85rem;">Manage Your Profile & Security</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-md-block">
                    <div class="fw-bold text-primary" style="font-size: 0.9rem;">{{ auth()->user()->name }}</div>
                </div>
            </div>
        </div>

        <div class="row g-3 g-md-4">
            <div class="col-lg-6">
                <div class="glass-card p-0 border-0 shadow-sm bg-white h-100 overflow-hidden">
                    <div class="p-3 p-md-4 border-bottom bg-light">
                        <h6 class="mb-0 fw-bold text-primary text-[14px] md:text-base">
                            <i class="bi bi-person-badge me-2"></i>Profile Information
                        </h6>
                    </div>
                    <div class="p-3 p-md-4">
                        <form wire:submit="updateProfile">
                            <div class="text-center mb-3 mb-md-4">
                                <div class="profile-avatar-lg mx-auto"
                                    style="width: 60px; height: 60px; font-size: 1.2rem;">
                                    {{ strtoupper(substr($name, 0, 2)) }}
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted uppercase text-[10px] md:text-[11px]">Full
                                    Name</label>
                                <input type="text" wire:model="name"
                                    class="form-control-modern py-1 py-md-2 @error('name') is-invalid @enderror"
                                    style="font-size: 0.9rem;">
                                @error('name')
                                    <div class="invalid-feedback text-[11px]">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3 mb-md-4">
                                <label class="form-label fw-bold text-muted uppercase text-[10px] md:text-[11px]">Email
                                    Address</label>
                                <input type="email" wire:model="email"
                                    class="form-control-modern py-1 py-md-2 @error('email') is-invalid @enderror"
                                    style="font-size: 0.9rem;">
                                @error('email')
                                    <div class="invalid-feedback text-[11px]">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn-glow w-100 py-2 text-[13px] md:text-sm fw-bold">
                                <span wire:loading.remove wire:target="updateProfile">Update Profile</span>
                                <span wire:loading wire:target="updateProfile">Updating...</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Security / Password Card --}}
            <div class="col-lg-6">
                <div class="glass-card p-0 border-0 shadow-sm bg-white h-100 overflow-hidden">
                    <div class="p-3 p-md-4 border-bottom bg-light">
                        <h6 class="mb-0 fw-bold text-primary text-[14px] md:text-base">
                            <i class="bi bi-shield-lock me-2"></i>Security Preferences
                        </h6>
                    </div>
                    <div class="p-3 p-md-4">
                        <form wire:submit="updatePassword">
                            {{-- Current Password --}}
                            <div class="mb-3">
                                <label
                                    class="form-label fw-bold text-muted uppercase text-[10px] md:text-[11px]">Current
                                    Password</label>
                                <div class="position-relative">
                                    <input :type="showCurrent ? 'text' : 'password'" wire:model="current_password"
                                        class="form-control-modern py-1 py-md-2 @error('current_password') is-invalid @enderror"
                                        style="font-size: 0.9rem;">
                                    <button type="button" @click="showCurrent = !showCurrent"
                                        class="btn-password-toggle py-1">
                                        <i class="bi" :class="showCurrent ? 'bi-eye-slash' : 'bi-eye'"
                                            style="font-size: 0.8rem;"></i>
                                    </button>
                                </div>
                                @error('current_password')
                                    <div class="text-danger mt-1 text-[10px] fw-bold">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- New Password --}}
                            <div class="mb-3">
                                <label class="form-label fw-bold text-muted uppercase text-[10px] md:text-[11px]">New
                                    Password</label>
                                <div class="position-relative">
                                    <input :type="showNew ? 'text' : 'password'" wire:model.live="password"
                                        @input="checkStrength($el.value)"
                                        class="form-control-modern py-1 py-md-2 @error('password') is-invalid @enderror"
                                        style="font-size: 0.9rem;">
                                    <button type="button" @click="showNew = !showNew" class="btn-password-toggle py-1">
                                        <i class="bi" :class="showNew ? 'bi-eye-slash' : 'bi-eye'"
                                            style="font-size: 0.8rem;"></i>
                                    </button>
                                </div>
                                {{-- Password Strength Meter: Thinner for mobile --}}
                                <div class="mt-2 d-flex gap-1">
                                    <template x-for="i in 4">
                                        <div class="flex-fill rounded-pill" style="height: 3px;"
                                            :style="i <= strength ? {
                                                background: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'][strength -
                                                    1
                                                ]
                                            } : { background: '#e2e8f0' }">
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Confirm Password --}}
                            <div class="mb-3 mb-md-4">
                                <label
                                    class="form-label fw-bold text-muted uppercase text-[10px] md:text-[11px]">Confirm
                                    Password</label>
                                <div class="position-relative">
                                    <input :type="showConfirm ? 'text' : 'password'" wire:model="password_confirmation"
                                        class="form-control-modern py-1 py-md-2" style="font-size: 0.9rem;">
                                    <button type="button" @click="showConfirm = !showConfirm"
                                        class="btn-password-toggle py-1">
                                        <i class="bi" :class="showConfirm ? 'bi-eye-slash' : 'bi-eye'"
                                            style="font-size: 0.8rem;"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn-glow w-100 py-2 text-[13px] md:text-sm fw-bold">
                                Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

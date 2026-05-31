<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Title;
use Livewire\Attributes\Layout;
use App\Traits\AuthenticatesLogout;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use App\Http\Requests\Admin\AdminSettingsRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

new #[Layout('layouts.admin')] #[Title('Settings')] class extends Component {
    use AuthenticatesLogout;

    public $user;

    public string $name = '';
    public string $email = '';

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount()
    {
        $this->user = auth()->user();

        if (!$this->user) {
            return redirect()->route('admin.login');
        }

        $this->name = $this->user->name;
        $this->email = $this->user->email;
    }

    public function updateProfile()
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized access.');
        }

        $this->authorize('admin');

        $validated = $this->validate(AdminSettingsRequest::profileRules());

        $this->user->update($validated);

        $this->dispatch('swal', [
            'title' => 'Admin Profile Updated',
            'text' => 'Your information is now up to date.',
            'icon' => 'success',
        ]);
    }

    public function updatePassword()
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Unauthorized access.');
        }

        $this->authorize('admin');

        $validated = $this->validate(AdminSettingsRequest::passwordRules());

        $this->user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        $this->dispatch('swal', [
            'title' => 'Security Updated',
            'text' => 'Admin password changed successfully.',
            'icon' => 'success',
        ]);
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
                        <form
                            @submit.prevent="
                                Swal.fire({
                                    title: 'Update Profile?',
                                    text: 'Are you sure you want to change your account details?',
                                    icon: 'question',
                                    showCancelButton: true,
                                    confirmButtonColor: '#0d6efd',
                                    confirmButtonText: 'Yes, Save'
                                }).then((result) => {
                                    if (result.isConfirmed) $wire.updateProfile()
                                })
                            ">
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

                            <x-button type="submit" variant="glow" class="w-100 py-2 text-[13px] md:text-sm fw-bold">
                                <span wire:loading.remove wire:target="updateProfile">Update Profile</span>
                                <span wire:loading wire:target="updateProfile">Updating...</span>
                            </x-button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="glass-card p-0 border-0 shadow-sm bg-white h-100 overflow-hidden">
                    <div class="p-3 p-md-4 border-bottom bg-light">
                        <h6 class="mb-0 fw-bold text-primary text-[14px] md:text-base">
                            <i class="bi bi-shield-lock me-2"></i>Security Preferences
                        </h6>
                    </div>
                    <div class="p-3 p-md-4">
                        <form
                            @submit.prevent="
                                Swal.fire({
                                    title: 'Change Password?',
                                    text: 'You will be required to re-authenticate if this is a sensitive session.',
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#0d6efd',
                                    confirmButtonText: 'Yes, Change'
                                }).then((result) => {
                                    if (result.isConfirmed) $wire.updatePassword()
                                })
                            ">
                            <div class="mb-3">
                                <label
                                    class="form-label fw-bold text-muted uppercase text-[10px] md:text-[11px]">Current
                                    Password</label>
                                <div class="position-relative">
                                    <input :type="showCurrent ? 'text' : 'password'" wire:model.blur="current_password"
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
                                <div class="mt-2 d-flex gap-1">
                                    <template x-for="i in 4">
                                        <div class="flex-fill rounded-pill" style="height: 3px;"
                                            :style="i <= strength ? {
                                                background: ['#ef4444', '#f59e0b', '#3b82f6', '#10b981'][
                                                    strength - 1
                                                ]
                                            } : { background: '#e2e8f0' }">
                                        </div>
                                    </template>
                                </div>
                                @error('password')
                                    <div class="text-danger mt-1 text-[10px] fw-bold">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3 mb-md-4">
                                <label
                                    class="form-label fw-bold text-muted uppercase text-[10px] md:text-[11px]">Confirm
                                    Password</label>
                                <div class="position-relative">
                                    <input :type="showConfirm ? 'text' : 'password'"
                                        wire:model.blur="password_confirmation" class="form-control-modern py-1 py-md-2"
                                        style="font-size: 0.9rem;">
                                    <button type="button" @click="showConfirm = !showConfirm"
                                        class="btn-password-toggle py-1">
                                        <i class="bi" :class="showConfirm ? 'bi-eye-slash' : 'bi-eye'"
                                            style="font-size: 0.8rem;"></i>
                                    </button>
                                </div>
                            </div>

                            <x-button type="submit" variant="glow"
                                class="w-100 py-2 text-[13px] md:text-sm fw-bold mb-11 md:mb-0">
                                <span wire:loading.remove wire:target="updatePassword">Change Password</span>
                                <span wire:loading wire:target="updatePassword">Updating...</span>
                            </x-button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

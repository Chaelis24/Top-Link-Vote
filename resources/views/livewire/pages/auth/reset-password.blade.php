<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset($this->only('email', 'password', 'password_confirmation', 'token'), function ($user) {
            $user
                ->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])
                ->save();

            event(new PasswordReset($user));
        });

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));
            return;
        }

        Session::flash('status', 'Your password has been reset successfully!');
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div>
    <div class="forgot-wrapper fade-in-up" wire:key="reset-password-container">
        <div class="forgot-card glass">
            <div class="forgot-glow-ring"></div>

            <div class="forgot-logo">
                <i class="bi bi-shield-lock-fill"></i>
            </div>

            <h1 class="forgot-title">Reset Password</h1>
            <p class="forgot-subtitle">Secure Your Account</p>

            <div class="reset-steps mb-4">
                <div class="reset-step completed"><span class="step-dot"></span>ID</div>
                <div class="reset-step-divider"></div>
                <div class="reset-step completed"><span class="step-dot"></span>Verify</div>
                <div class="reset-step-divider"></div>
                <div class="reset-step active"><span class="step-dot"></span>Reset</div>
            </div>

            <p class="forgot-desc text-center">Please enter your new password below.</p>

            @error('email')
                <div class="alert alert-danger border-0 bg-danger text-white small p-2 mb-3 text-center"
                    style="border-radius: 8px;">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ $message }}
                </div>
            @enderror

            <form wire:submit.prevent="resetPassword"> {{-- .prevent para sigurado --}}
                <div class="form-floating-custom mb-3" style="opacity: 0.7;">
                    <input type="email" wire:model="email" placeholder="Email" readonly tabindex="-1">
                    <i class="bi bi-envelope input-icon"></i>
                </div>

                <div class="form-floating-custom mb-2">
                    <input type="password" wire:model="password" placeholder="New Password" required autofocus>
                    <i class="bi bi-lock-fill input-icon"></i>
                </div>
                <div style="min-height: 20px;">
                    @error('password')
                        <span class="text-danger small d-block mb-2" style="font-size: 0.75rem;">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-floating-custom mb-4">
                    <input type="password" wire:model="password_confirmation" placeholder="Confirm New Password"
                        required>
                    <i class="bi bi-check-circle-fill input-icon"></i>
                </div>

                <button type="submit" class="btn btn-glow btn-reset w-100" wire:loading.attr="disabled">
                    <span wire:loading.remove>
                        <i class="bi bi-shield-check me-2"></i>Update Password
                    </span>
                    <span wire:loading>
                        <span class="spinner-border spinner-border-sm me-2"></span>Processing...
                    </span>
                </button>
            </form>

            <div class="back-link mt-4">
                <a href="{{ url('/') }}" wire:navigate><i class="bi bi-arrow-left me-1"></i>Cancel and Return</a>
            </div>
        </div>
    </div>
</div>

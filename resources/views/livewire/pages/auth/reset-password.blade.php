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

            <h1 class="forgot-title">Forgot Password</h1>
            <p class="forgot-subtitle">Account Recovery</p>

            <div class="reset-steps mb-4">
                <div class="reset-step completed"><span class="step-dot"></span>Enter ID</div>
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

            <form wire:submit.prevent="resetPassword">
                <div class="form-floating-custom mb-3" style="opacity: 0.6; cursor: not-allowed;">
                    <div class="position-relative">
                        <i class="bi bi-envelope-fill position-absolute"
                            style="left: 15px; top: 50%; transform: translateY(-50%); color: #888;"></i>
                        <input type="email" wire:model="email" class="custom-input" placeholder=" " readonly
                            tabindex="-1">
                    </div>
                </div>

                <div class="mb-2" x-data="{ show: false }">
                    <div class="position-relative">
                        <i class="bi bi-lock-fill position-absolute"
                            style="left: 15px; top: 50%; transform: translateY(-50%); color: #888; z-index: 5;"></i>

                        <input :type="show ? 'text' : 'password'" wire:model="password"
                            class="custom-input @error('password') border-danger @enderror" placeholder="New Password"
                            required autofocus>

                        <i class="bi" :class="show ? 'bi-eye-slash-fill' : 'bi-eye-fill'" @click="show = !show"
                            style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888; z-index: 10;">
                        </i>
                    </div>

                    <div style="min-height: 20px;">
                        @error('password')
                            <span class="text-danger" style="font-size: 0.75rem;">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="mb-4" x-data="{ show: false }">
                    <div class="position-relative">
                        <i class="bi bi-shield-check position-absolute"
                            style="left: 15px; top: 50%; transform: translateY(-50%); color: #888; z-index: 5;"></i>

                        <input :type="show ? 'text' : 'password'" wire:model="password_confirmation"
                            class="custom-input" placeholder="Confirm New Password" required>

                        <i class="bi" :class="show ? 'bi-eye-slash-fill' : 'bi-eye-fill'" @click="show = !show"
                            style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #888; z-index: 10;">
                        </i>
                    </div>
                </div>

                <button type="submit" class="btn-glow btn-reset w-100 py-2 fw-bold" wire:loading.attr="disabled"
                    style="background: #28a745; border: none; color: white; border-radius: 8px; transition: 0.3s;">
                    <span wire:loading.remove>
                        <i class="bi bi-check2-circle me-2"></i>Update Password
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

<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $email = '';
    public string $student_id = '';
    public bool $isSent = false;

    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'student_id' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        $student = \App\Models\Student::where('student_id', $this->student_id)->first();

        if (!$student || $student->user?->email !== $this->email) {
            $this->addError('student_id', 'The provided details do not match our records.');
            return;
        }

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->isSent = true;
        session()->flash('status', __($status));
    }
}; ?>

<div>
    <div class="forgot-wrapper fade-in-up delay-1">
        <div class="forgot-card glass fade-in-up" data-aos="zoom-in" data-aos-duration="1000">
            <div class="forgot-glow-ring"></div>

            <div class="forgot-logo">
                <i class="bi bi-key-fill"></i>
            </div>

            <h1 class="forgot-title">Forgot Password</h1>
            <p class="forgot-subtitle">Account Recovery</p>

            <div class="reset-steps">
                <div class="reset-step {{ !$isSent ? 'active' : 'completed' }}"
                    style="{{ $isSent ? 'opacity: 0.6;' : '' }}">
                    <span class="step-dot"></span>Enter ID
                </div>
                <div class="reset-step-divider"></div>

                <div class="reset-step {{ $isSent ? 'active' : '' }}">
                    <span class="step-dot"></span>Verify
                </div>
                <div class="reset-step-divider"></div>

                <div class="reset-step">
                    <span class="step-dot"></span>Reset
                </div>
            </div>

            @if ($errors->any())
                <div class="error-msg"
                    style="color: #ef4444; font-size: 0.875rem; margin-bottom: 1rem; text-align: center;">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            @if (!$isSent)
                <p class="forgot-desc">
                    Enter your Student ID and registered email address. We'll send you a verification link to reset your
                    password.
                </p>

                <form wire:submit="sendPasswordResetLink">
                    <div class="form-floating-custom mb-3"
                        style="position: relative; display: flex; align-items: center;">
                        <i class="bi bi-person-badge input-icon-style"></i>
                        <input type="text" wire:model="student_id" placeholder="Student ID Number"
                            class="custom-input" required>
                    </div>

                    <div class="form-floating-custom mb-4"
                        style="position: relative; display: flex; align-items: center;">
                        <i class="bi bi-envelope input-icon-style"></i>
                        <input type="email" wire:model="email" placeholder="Registered Email Address"
                            class="custom-input" required>
                    </div>

                    <button type="submit" class="btn btn-glow btn-reset">
                        <span wire:loading.remove wire:target="sendPasswordResetLink">
                            <i class="bi bi-send me-2"></i>Send Reset Link
                        </span>
                        <span wire:loading wire:target="sendPasswordResetLink">
                            <span class="spinner-border spinner-border-sm me-2" role="status"></span>Sending...
                        </span>
                    </button>
                </form>
            @else
                <div class="success-state" style="text-align: center; margin: 2rem 0;">
                    <i class="bi bi-envelope-check"
                        style="font-size: 3.5rem; color: #388e3c; margin-bottom: 15px; display: inline-block;"></i>
                    <h3 style="color: #388e3c; font-size: 1.2rem; margin-bottom: 10px;">Link Sent Successfully!</h3>
                    <p class="forgot-desc" style="margin-bottom: 0;">
                        Please check your email (<b>{{ $email }}</b>) and click the reset link to proceed to the
                        final step.
                    </p>

                    <button type="button" wire:click="$set('isSent', false)" class="btn-register text-white"
                        style="margin-top: 20px; background: transparent; border: none; color: #388e3c; cursor: pointer; text-decoration: underline;">
                        Didn't receive it? Try again
                    </button>
                </div>
            @endif

            <div class="back-link">
                <a href="{{ url('/') }}" wire:navigate><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
            </div>
        </div>
    </div>
</div>

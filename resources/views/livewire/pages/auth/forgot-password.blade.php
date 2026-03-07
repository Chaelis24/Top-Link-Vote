<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $email = '';
    public string $student_id = '';

    /**
     * Send a password reset link.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'student_id' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        $user = \App\Models\User::where('email', $this->email)->where('student_id', $this->student_id)->first();

        if (!$user) {
            $this->addError('student_id', 'The provided details do not match our records.');
            return;
        }

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->reset(['email', 'student_id']);
        session()->flash('status', __($status));
    }
}; ?>

<div>
    <div class="forgot-wrapper">
        <div class="forgot-card glass fade-in-up" data-aos="zoom-in" data-aos-duration="1000">
            <div class="forgot-glow-ring"></div>

            <div class="forgot-logo">
                <i class="bi bi-key-fill"></i>
            </div>

            <h1 class="forgot-title">Forgot Password</h1>
            <p class="forgot-subtitle">Account Recovery</p>

            {{-- Step Indicators --}}
            <div class="reset-steps">
                <div class="reset-step active">
                    <span class="step-dot"></span>Enter ID
                </div>
                <div class="reset-step-divider"></div>
                <div class="reset-step">
                    <span class="step-dot"></span>Verify
                </div>
                <div class="reset-step-divider"></div>
                <div class="reset-step">
                    <span class="step-dot"></span>Reset
                </div>
            </div>

            <p class="forgot-desc">
                Enter your Student ID and registered email address. We'll send you a verification link to reset your
                password.
            </p>

            {{-- Error Messages --}}
            @if ($errors->any())
                <div class="error-msg">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Success Message --}}
            @if (session('status'))
                <div class="success-msg">
                    <i class="bi bi-check-circle me-1"></i>
                    {{ session('status') }}
                </div>
            @endif

            {{-- Forgot Password Form --}}
            <form wire:submit="sendPasswordResetLink">
                <div class="form-floating-custom">
                    <input type="text" wire:model="student_id" placeholder="Student ID Number" required
                        autocomplete="username">
                    <i class="bi bi-person-badge input-icon"></i>
                </div>

                <div class="form-floating-custom">
                    <input type="email" wire:model="email" placeholder="Registered Email Address" required>
                    <i class="bi bi-envelope input-icon"></i>
                </div>

                <button type="submit" class="btn btn-glow btn-reset">
                    <span wire:loading.remove>
                        <i class="bi bi-send me-2"></i>Send Reset Link
                    </span>
                    <span wire:loading>
                        <span class="spinner-border spinner-border-sm me-2" role="status"></span>Sending...
                    </span>
                </button>
            </form>

            <div class="back-link">
                <a href="{{ url('/') }}" wire:navigate><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
            </div>
        </div>
    </div>
</div>

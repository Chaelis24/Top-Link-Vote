<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        try {
            $this->validate();

            $this->form->authenticate();

            $user = auth()->user();

            if ($user->roles()->where('name', 'admin')->exists()) {
                $this->redirectIntended(route('admin.dashboard'), navigate: true);
                return;
            }

            $this->redirectIntended(route('student.dashboard'), navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->form->password = '';
            $this->dispatch('auth-failed');

            throw $e;
        }
    }
}; ?>

<div>
    <div class="login-wrapper">
        <div class="login-card glass fade-in-up delay-1">
            <div class="login-glow-ring"></div>

            <div class="login-logo">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="img-fluid main-logo">
            </div>

            <h1 class="login-title">Top Link-Vote</h1>
            <p class="login-desc">Please log in to cast your vote.</p>

            @if ($errors->any())
                <div class="error-msg">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form wire:submit="login">
                <div class="form-floating-custom">
                    <input type="text" id="student_id" wire:model="form.student_id" placeholder=" " required>
                    <label for="student_id">Student ID Number</label>
                    <i class="bi bi-person-badge input-icon"></i>
                </div>

                <div class="form-floating-custom" style="position: relative;" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" id="passwordInput" wire:model="form.password"
                        placeholder=" " required>
                    <label for="passwordInput">Password</label>
                    <i class="bi bi-lock input-icon"></i>

                    <i class="bi" :class="show ? 'bi-eye' : 'bi-eye-slash'" @click="show = !show"
                        style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; opacity: 0.7;">
                    </i>
                </div>

                <div class="remember-row">
                    <div class="remember-check">
                        <input type="checkbox" wire:model="form.remember" id="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="{{ route('forgot-password') }}" wire:navigate class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-glow btn-login">
                    <span wire:loading.remove>
                        <i class="bi bi-box-arrow-in-right me-2"></i>LOGIN
                    </span>
                    <span wire:loading>
                        Logging in...
                    </span>
                </button>
            </form>

            <div class="login-divider"><span>or</span></div>

            <a href="/verify-account" class="btn-register" wire:navigate>
                <i class="bi bi-person-plus-fill"></i>Verify an Account
            </a>
        </div>
    </div>
</div>

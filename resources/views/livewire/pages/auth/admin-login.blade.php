<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public LoginForm $form;

    /**
     * Handle an admin authentication request.
     */
    public function login(): void
    {
        try {
            $this->validate([
                'form.student_id' => 'required', // Gagamitin natin ito as Email input
                'form.password' => 'required',
            ]);

            $this->form->authenticate();

            $user = auth()->user();

            // Guard: Siguraduhin na Admin lang ang makakapasok dito
            if (!$user->roles()->where('name', 'admin')->exists()) {
                auth()->logout();
                $this->addError('form.student_id', 'Unauthorized access. This portal is for administrators only.');
                return;
            }

            Session::regenerate();

            $this->redirectIntended(route('admin.dashboard'), navigate: true);
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
            <div class="login-glow-ring" style="border-color: #ff4757;"></div>
            <div class="login-logo">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="img-fluid main-logo">
            </div>

            <h1 class="login-title">Admin Login</h1>
            <p class="login-desc">Secure access for system administrators.</p>

            @if ($errors->any())
                <div class="error-msg">
                    <i class="bi bi-shield-lock-fill me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form wire:submit="login">
                <div class="form-floating-custom">
                    <input type="text" id="admin_email" wire:model="form.student_id" placeholder=" " required>
                    <label for="admin_email">Administrator Email</label>
                    <i class="bi bi-person-workspace input-icon"></i>
                </div>

                <div class="form-floating-custom" style="position: relative;" x-data="{ show: false }">
                    <input :type="show ? 'text' : 'password'" id="passwordInput" wire:model="form.password"
                        placeholder=" " required>
                    <label for="passwordInput">Administrator Password</label>
                    <i class="bi bi-shield-lock input-icon"></i> <i class="bi"
                        :class="show ? 'bi-eye' : 'bi-eye-slash'" @click="show = !show"
                        style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; opacity: 0.7;">
                    </i>
                </div>

                <div class="remember-row">
                    <div class="remember-check">
                        <input type="checkbox" wire:model="form.remember" id="remember">
                        <label for="remember">Remember session</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-glow btn-login"
                    style="background: linear-gradient(45deg, #ff4757, #ff6b81);">
                    <span wire:loading.remove>
                        <i class="bi bi-speedometer2 me-2"></i>ENTER DASHBOARD
                    </span>
                    <span wire:loading>
                        Authenticating...
                    </span>
                </button>
            </form>

            <div class="login-divider"><span>System Security</span></div>

            <div class="text-center mt-3">
                <a href="/" class="text-white text-decoration-none small" wire:navigate>
                    <i class="bi bi-arrow-left"></i> Back to Student Login
                </a>
            </div>
        </div>
    </div>
</div>

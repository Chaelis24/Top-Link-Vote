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
        $this->validate();

        $this->form->authenticate();

        $user = auth()->user();

        if ($user->role === 'admin') {
            $this->redirectIntended(route('admin.dashboard'), navigate: true);
            return;
        }

        $this->redirectIntended(route('student.dashboard'), navigate: true);
    }
}; ?>

<div>
    <style>
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            padding: 48px 40px;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .login-logo {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 2rem;
            box-shadow: 0 10px 30px rgba(56, 142, 60, 0.3);
            animation: logoPulse 3s ease-in-out infinite;
        }

        @keyframes logoPulse {

            0%,
            100% {
                box-shadow: 0 10px 30px rgba(56, 142, 60, 0.3);
            }

            50% {
                box-shadow: 0 10px 50px rgba(56, 142, 60, 0.5);
            }
        }

        .login-title {
            font-size: 2.2rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            background: linear-gradient(to right, #388E3C, #673AB7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }

        .login-desc {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 32px;
        }

        .form-floating-custom {
            position: relative;
            margin-bottom: 20px;
        }

        .form-floating-custom input {
            width: 100%;
            padding: 16px 16px 16px 48px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 0.95rem;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            outline: none;
        }

        /* Pinalitan ang focus color mula red papuntang green */
        .form-floating-custom input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(56, 142, 60, 0.15);
            background: rgba(255, 255, 255, 0.08);
        }

        .form-floating-custom input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .form-floating-custom .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1.1rem;
            transition: color 0.3s;
        }

        .form-floating-custom input:focus~.input-icon {
            color: #388E3C;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            font-size: 1.2rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            margin-top: 15px;
            border: none;
            border-radius: 50px;
            color: white;
            background: linear-gradient(to right, #388E3C, #673AB7) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: scale(1.02);
            opacity: 0.9;
            box-shadow: 0 6px 20px rgba(103, 58, 183, 0.4) !important;
        }

        .login-glow-ring {
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            border-radius: 18px;
            background: linear-gradient(135deg, #388E3C, #673AB7, #388E3C);
            background-size: 300% 300%;
            z-index: -1;
            opacity: 0.3;
            animation: glowRotate 6s ease infinite;
            filter: blur(2px);
        }

        @keyframes glowRotate {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .error-msg {
            background: rgba(103, 58, 183, 0.1);
            border: 1px solid rgba(103, 58, 183, 0.3);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            color: #b39ddb;
            text-align: left;
        }

        /* Remember Me */
        .remember-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .remember-check {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .remember-check input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
            cursor: pointer;
        }

        .remember-check label {
            color: var(--text-secondary);
            font-size: 0.82rem;
            cursor: pointer;
            user-select: none;
        }

        .forgot-link {
            color: var(--accent);
            font-size: 0.82rem;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .forgot-link:hover {
            color: var(--accent-glow);
            text-decoration: underline;
        }

        /* Divider */
        .login-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0;
        }

        .login-divider::before,
        .login-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .login-divider span {
            color: var(--text-secondary);
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Register Button */
        .btn-register {
            width: 100%;
            padding: 14px;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            background: transparent;
            border: 1px solid var(--purple);
            color: var(--purple);
            border-radius: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-register:hover {
            background: var(--purple);
            color: #fff;
            box-shadow: 0 10px 30px rgba(103, 58, 183, 0.4);
            transform: translateY(-2px);
        }
    </style>

    <div class="login-wrapper">
        <div class="login-card glass fade-in-up">
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
                    <input type="text" wire:model="form.student_id" placeholder="Student ID Number" required>
                    <i class="bi bi-person-badge input-icon"></i>
                </div>

                <div class="form-floating-custom">
                    <input type="password" wire:model="form.password" placeholder="Password" required>
                    <i class="bi bi-lock input-icon"></i>
                </div>

                <div class="remember-row">
                    <div class="remember-check">
                        <input type="checkbox" wire:model="form.remember" id="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-link">Forgot password?</a>
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

            <a href="/register" class="btn-register" wire:navigate>
                <i class="bi bi-person-plus-fill"></i>Create an Account
            </a>
        </div>
    </div>
</div>

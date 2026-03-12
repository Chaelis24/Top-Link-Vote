<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

new #[Layout('layouts.guest')] class extends Component {
    #[Validate('required|string|exists:students,student_id')]
    public string $student_id = '';

    #[Validate('required|date')]
    public string $birthday = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function verifyAndSetPassword()
    {
        $this->validate();

        $student = Student::with('user')->where('student_id', $this->student_id)->first();

        $dbBirthday = $student->birthday instanceof Carbon ? $student->birthday->format('Y-m-d') : $student->birthday;

        if ($dbBirthday !== $this->birthday) {
            throw ValidationException::withMessages([
                'birthday' => 'Invalid birthday for this Student ID.',
            ]);
        }

        $user = $student->user;

        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'student_id' => 'This account is already verified. You can now login.',
            ]);
        }

        $user->password = Hash::make($this->password);
        $user->email_verified_at = now();
        $user->save();

        Auth::login($user);

        $this->redirectIntended(route('student.dashboard'), navigate: true);
    }
};

?>

<div>
    <div class="login-wrapper">
        <div class="login-card glass fade-in-up delay-1">
            <div class="login-glow-ring"></div>

            <div class="login-logo">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="img-fluid main-logo">
            </div>

            <h1 class="login-title">Verify Account</h1>
            <p class="login-desc">Please verify your details and set a new password.</p>

            @if ($errors->any())
                <div class="error-msg">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            <form wire:submit="verifyAndSetPassword">
                <div class="form-floating-custom">
                    <input type="text" wire:model="student_id" placeholder="Student ID Number" required>
                    <i class="bi bi-person-badge input-icon"></i>
                </div>

                <div class="form-floating-custom">
                    <input type="date" wire:model="birthday" placeholder="Birthday" required>
                    <i class="bi bi-calendar-date input-icon"></i>
                </div>

                <div class="form-floating-custom">
                    <input type="password" wire:model="password" placeholder="New Password" required>
                    <i class="bi bi-lock input-icon"></i>
                </div>

                <div class="form-floating-custom mb-4">
                    <input type="password" wire:model="password_confirmation" placeholder="Confirm Password" required>
                    <i class="bi bi-shield-lock input-icon"></i>
                </div>

                <button type="submit" class="btn btn-glow btn-login">
                    <span wire:loading.remove>
                        <i class="bi bi-check-circle me-2"></i>VERIFY & LOGIN
                    </span>
                    <span wire:loading>
                        Verifying...
                    </span>
                </button>
            </form>

            <div class="login-divider"><span>or</span></div>

            <a href="{{ route('login') }}" class="btn-register" wire:navigate>
                <i class="bi bi-arrow-left"></i>Back to Login
            </a>
        </div>
    </div>
</div>

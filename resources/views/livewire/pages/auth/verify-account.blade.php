<?php

use Livewire\Volt\Component;
use Livewire\Attributes\{Layout, Validate};
use App\Models\{Student, User};
use Illuminate\Support\Facades\{Hash, Auth, Cache, Mail};
use Illuminate\Validation\ValidationException;
use App\Mail\OtpVerificationMail;

new #[Layout('layouts.guest')] class extends Component {
    public string $student_id = '';
    public string $code = '';
    public string $password = '';
    public string $password_confirmation = '';

    public int $step = 1;
    public string $maskedEmail = '';

    public function sendOtp()
    {
        $this->validate([
            'student_id' => 'required|string|exists:students,student_id',
        ]);

        $student = Student::with('user')->where('student_id', $this->student_id)->first();
        $user = $student->user;

        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'student_id' => 'This account is already verified. You can now login.',
            ]);
        }

        $otp = rand(100000, 999999);
        Cache::put('otp_student_' . $this->student_id, $otp, now()->addMinutes(10));

        Mail::to($user->email)->send(new OtpVerificationMail($otp));

        $parts = explode('@', $user->email);
        $this->maskedEmail = substr($parts[0], 0, 2) . str_repeat('*', strlen($parts[0]) - 2) . '@' . $parts[1];

        $this->step = 2;
        session()->flash('status', 'A verification code has been sent to your registered email.');
    }

    public function verifyOtp()
    {
        $this->validate([
            'code' => 'required|numeric|digits:6',
        ]);

        $cachedCode = Cache::get('otp_student_' . $this->student_id);

        if (!$cachedCode || $cachedCode != $this->code) {
            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid or has expired.',
            ]);
        }

        $this->step = 3;
        session()->flash('status', 'Account verified! You can now set your new password.');
    }

    public function setPassword()
    {
        $this->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $student = Student::with('user')->where('student_id', $this->student_id)->first();
        $user = $student->user;

        $user->password = Hash::make($this->password);
        $user->email_verified_at = now();
        $user->save();

        Cache::forget('otp_student_' . $this->student_id);

        Auth::login($user);

        $this->redirectIntended('/', navigate: true);
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

            @if ($step === 1)
                <p class="login-desc">Enter your Student ID to receive a verification code.</p>
            @elseif($step === 2)
                <p class="login-desc">Enter the 6-digit code sent to <strong>{{ $maskedEmail }}</strong>.</p>
            @elseif($step === 3)
                <p class="login-desc">Create a secure password for your account.</p>
            @endif

            @if (session('status'))
                <div class="success-msg"
                    style="color: #10b981; font-size: 0.875rem; margin-bottom: 1rem; text-align: center;">
                    <i class="bi bi-check-circle me-1"></i>
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="error-msg"
                    style="color: #ef4444; font-size: 0.875rem; margin-bottom: 1rem; text-align: center;">
                    <i class="bi bi-exclamation-ci759025rcle me-1"></i>
                    {{ $errors->first() }}
                </div>
            @endif

            @if ($step === 1)
                <form wire:submit="sendOtp">
                    <div class="form-floating-custom mb-4"
                        style="display: flex; align-items: center; position: relative;">
                        <i class="bi bi-person-badge input-icon"
                            style="position: absolute; left: 15px; font-size: 1.2rem;"></i>

                        <input type="text" wire:model="student_id" placeholder="Student ID Number"
                            style="width: 100%; padding: 12px 12px 12px 45px; text-align: left;" required autofocus>
                    </div>

                    <button type="submit" class="btn btn-glow btn-login">
                        <span wire:loading.remove wire:target="sendOtp">
                            <i class="bi bi-person-badge me-2"></i>ENTER STUDENT ID
                        </span>
                        <span wire:loading wire:target="sendOtp">
                            Entering...
                        </span>
                    </button>
                </form>
            @endif

            @if ($step === 2)
                <form wire:submit.prevent="verifyOtp">
                    <div class="position-relative mb-4">
                        <i class="bi bi-123 position-absolute"
                            style="left: 15px; top: 50%; transform: translateY(-50%); color: #888; font-size: 1.2rem; z-index: 5;"></i>

                        <input type="text" wire:model="code" class="custom-input" placeholder="6-Digit OTP Code"
                            required autofocus autocomplete="one-time-code">
                    </div>

                    <button type="submit" class="btn btn-glow btn-login w-100">
                        <span wire:loading.remove wire:target="verifyOtp">
                            <i class="bi bi-check-circle me-2"></i>VERIFY CODE
                        </span>
                        <span wire:loading wire:target="verifyOtp">
                            Verifying...
                        </span>
                    </button>

                    <div class="mt-4 text-center">
                        <button type="button" wire:click="sendOtp" class="btn-register"
                            style="background: transparent; border: none; padding: 0; box-shadow: none; font-size: 0.9rem; opacity: 0.8; transition: opacity 0.3s ease;">
                            <span wire:loading.remove wire:target="sendOtp">
                                <i class="bi bi-arrow-clockwise me-1"></i> Didn't receive code? Resend
                            </span>
                            <span wire:loading wire:target="sendOtp">
                                <i class="bi bi-hourglass-split me-1"></i> Sending new code...
                            </span>
                        </button>
                    </div>
                </form>
            @endif

            @if ($step === 3)
                <form wire:submit="setPassword">
                    <div class="position-relative mb-3" x-data="{ show: false }">
                        <i class="bi bi-lock position-absolute"
                            style="left: 15px; top: 50%; transform: translateY(-50%); color: #888; z-index: 5;"></i>

                        <input :type="show ? 'text' : 'password'" wire:model="password" class="custom-input"
                            placeholder="New Password" required autofocus>

                        <i class="bi" :class="show ? 'bi-eye' : 'bi-eye-slash'" @click="show = !show"
                            style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; color: #888; opacity: 0.7;">
                        </i>
                    </div>

                    <div class="position-relative mb-4" x-data="{ show: false }">
                        <i class="bi bi-shield-lock position-absolute"
                            style="left: 15px; top: 50%; transform: translateY(-50%); color: #888; z-index: 5;"></i>

                        <input :type="show ? 'text' : 'password'" wire:model="password_confirmation"
                            class="custom-input" placeholder="Confirm Password" required>

                        <i class="bi" :class="show ? 'bi-eye' : 'bi-eye-slash'" @click="show = !show"
                            style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; z-index: 10; color: #888; opacity: 0.7;">
                        </i>
                    </div>

                    <button type="submit" class="btn btn-glow btn-login">
                        <span wire:loading.remove wire:target="setPassword">
                            <i class="bi bi-box-arrow-in-right me-2"></i>SAVE & LOGIN
                        </span>
                        <span wire:loading wire:target="setPassword">
                            Saving...
                        </span>
                    </button>
                </form>
            @endif

            <div class="back-link">
                <a href="{{ url('/') }}" wire:navigate><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
            </div>
        </div>
    </div>
</div>

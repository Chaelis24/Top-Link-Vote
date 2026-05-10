<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\{Hash, Auth, Cache, Mail};
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\{Layout, Validate};
use App\Mail\OtpVerificationMail;
use App\Models\{Student, User};

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
        $this->dispatch('swal:toast', [
            'icon' => 'success',
            'title' => 'OTP sent successfully!',
        ]);
    }

    public function verifyOtp()
    {
        $this->validate([
            'code' => 'required|numeric|digits:6',
        ]);

        $cachedCode = Cache::get('otp_student_' . $this->student_id);

        if (!$cachedCode || $cachedCode != $this->code) {
            $this->dispatch('swal:modal', [
                'icon' => 'error',
                'title' => 'Invalid OTP',
                'text' => 'The verification code is invalid or has expired.',
            ]);
            return;
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

        $this->redirectIntended(route('student.dashboard'));
    }
};

?>

<div class="min-h-screen w-full flex items-center justify-center bg-white p-4 m-0 font-['Raleway']">
    <div
        class="max-w-4xl w-full bg-white shadow-2xl rounded-2xl overflow-hidden flex flex-col md:flex-row border border-gray-100 mx-2 md:mx-0">

        <div
            class="md:w-1/2 bg-[linear-gradient(115deg,#0dff00,#068a08,#010d05)] p-6 md:p-12 text-white flex flex-col justify-center relative overflow-hidden min-h-[160px] md:min-h-[450px]">
            <div class="absolute -top-24 -left-24 w-64 h-64 bg-black/10 rounded-full"></div>
            <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-white/20 rounded-full"></div>

            <div class="relative z-10 flex flex-col items-center text-center">
                <div class="mb-4 md:mb-8">
                    <img src="{{ asset('images/logo.png') }}" alt="Top Link Logo" class="w-24 md:w-48 h-auto">
                </div>

                <h2
                    class="text-xl md:text-3xl font-extrabold uppercase mb-2 md:mb-4 tracking-tight text-white drop-shadow-md">
                    Top Link-Vote
                </h2>

                <p class="hidden md:block text-white leading-relaxed text-sm font-bold">
                    Empowering the student body through a transparent and secure digital ballot.
                    Step into the future of campus democracy.
                </p>
            </div>
        </div>

        <div class="md:w-1/2 p-6 md:p-10 flex flex-col justify-center bg-white">
            <div class="mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-[#252525] mb-1 md:mb-2 tracking-tighter">Verify Account
                </h2>
                <p class="text-gray-500 text-xs md:text-sm">
                    @if ($step === 1)
                        Enter your Student ID to start.
                    @elseif($step === 2)
                        Enter the code sent to {{ $maskedEmail }}.
                    @elseif($step === 3)
                        Create your new secure password.
                    @endif
                </p>
            </div>

            @if (session('status'))
                <div
                    class="mb-4 text-[#108500] text-[10px] md:text-xs font-bold uppercase p-3 bg-green-50 rounded-lg border border-green-100">
                    {{ session('status') }}
                </div>
            @endif

            @if ($step === 1)
                <form wire:submit="sendOtp" class="space-y-4 md:space-y-5">
                    <div>
                        <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">Student
                            ID</label>
                        <input wire:model="student_id" type="text" placeholder="e.g. 23-0001" required autofocus
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-[#252525] text-sm">
                        <x-input-error :messages="$errors->get('student_id')" class="mt-2 text-red-600 text-[10px]" />
                    </div>

                    <button type="submit"
                        class="w-full bg-[#108500] hover:bg-[#0d6b00] text-white font-black py-3 md:py-4 rounded-lg shadow-lg shadow-green-200 transition-all uppercase tracking-widest text-xs md:text-sm">
                        <span wire:loading.remove wire:target="sendOtp">Get Verification Code</span>
                        <span wire:loading wire:target="sendOtp">Sending Code...</span>
                    </button>
                </form>
            @endif

            @if ($step === 2)
                <form wire:submit.prevent="verifyOtp" class="space-y-4 md:space-y-5">
                    <div>
                        <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">6-Digit
                            Code</label>
                        <input wire:model="code" type="text" maxlength="6" placeholder="000000" required autofocus
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-center text-xl md:text-2xl font-bold tracking-[0.3em] md:tracking-[0.5em] text-[#252525]">
                        <x-input-error :messages="$errors->get('code')" class="mt-2 text-red-600 text-[10px]" />
                    </div>

                    <button type="submit"
                        class="w-full bg-[#108500] hover:bg-[#0d6b00] text-white font-black py-3 md:py-4 rounded-lg shadow-lg shadow-green-200 transition-all uppercase tracking-widest text-xs md:text-sm">
                        <span wire:loading.remove wire:target="verifyOtp">Verify OTP</span>
                        <span wire:loading wire:target="verifyOtp">Checking...</span>
                    </button>

                    <button type="button" wire:click="sendOtp"
                        class="w-full text-[10px] md:text-xs font-bold text-gray-400 uppercase hover:text-[#108500] transition-colors">
                        Didn't receive it? Resend Code
                    </button>
                </form>
            @endif

            @if ($step === 3)
                <form wire:submit="setPassword" class="space-y-4 md:space-y-5">
                    <div x-data="{ show: false }" class="relative">
                        <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">New
                            Password</label>
                        <input :type="show ? 'text' : 'password'" wire:model="password" placeholder="••••••••" required
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-[#252525] text-sm">
                        <button type="button" @click="show = !show"
                            class="absolute right-3 top-[32px] md:top-[38px] text-[9px] md:text-[10px] font-black text-[#108500] uppercase">
                            <span x-text="show ? 'Hide' : 'Show'"></span>
                        </button>
                        <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-600 text-[10px]" />
                    </div>

                    <div x-data="{ show: false }" class="relative">
                        <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">Confirm
                            Password</label>
                        <input :type="show ? 'text' : 'password'" wire:model="password_confirmation"
                            placeholder="••••••••" required
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-[#252525] text-sm">
                        <button type="button" @click="show = !show"
                            class="absolute right-3 top-[32px] md:top-[38px] text-[9px] md:text-[10px] font-black text-[#108500] uppercase">
                            <span x-text="show ? 'Hide' : 'Show'"></span>
                        </button>
                    </div>

                    <button type="submit"
                        class="w-full bg-[#108500] hover:bg-[#0d6b00] text-white font-black py-3 md:py-4 rounded-lg shadow-lg shadow-green-200 transition-all uppercase tracking-widest text-xs md:text-sm">
                        <span wire:loading.remove wire:target="setPassword">Verify Account</span>
                        <span wire:loading wire:target="setPassword">Saving...</span>
                    </button>
                </form>
            @endif

            <div class="mt-6 md:mt-8 text-center">
                <a href="{{ route('login') }}" wire:navigate
                    class="text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-widest hover:text-[#108500] flex items-center justify-center">
                    <svg class="w-4 h-4 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Back to login
                </a>
            </div>
        </div>
    </div>
</div>

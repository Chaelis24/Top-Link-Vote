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
            $this->dispatch('swal:modal', [
                'icon' => 'error',
                'title' => 'Oops!',
                'text' => 'The provided details do not match our records.',
            ]);
            return;
        }

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->isSent = true;
        $this->dispatch('swal:toast', [
            'icon' => 'success',
            'title' => 'Reset link sent to your email!',
        ]);
    }
}; ?>

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
                    Recovery
                </h2>

                <p class="hidden md:block text-white leading-relaxed text-sm font-bold">
                    Don't worry, it happens to the best of us.
                    Provide your details to regain access to your voting account.
                </p>
            </div>
        </div>

        <div class="md:w-1/2 p-6 md:p-10 flex flex-col justify-center bg-white">
            <div class="mb-6 md:mb-8 text-center md:text-left">
                <h2 class="text-2xl md:text-3xl font-bold text-[#252525] mb-1 md:mb-2 tracking-tighter">Forgot Password?
                </h2>
                <p class="text-gray-500 text-xs md:text-sm">Account Recovery Process</p>
            </div>

            <div class="flex items-center justify-between mb-6 md:mb-8 px-2">
                <div class="flex flex-col items-center">
                    <div
                        class="w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center text-[10px] md:text-xs font-bold {{ !$isSent ? 'bg-[#108500] text-white' : 'bg-green-100 text-[#108500]' }}">
                        1</div>
                    <span class="text-[9px] md:text-[10px] mt-1 font-bold uppercase text-gray-400">Details</span>
                </div>
                <div class="flex-1 h-[2px] mx-1 md:mx-2 {{ $isSent ? 'bg-[#108500]' : 'bg-gray-100' }}"></div>
                <div class="flex flex-col items-center">
                    <div
                        class="w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center text-[10px] md:text-xs font-bold {{ $isSent ? 'bg-[#108500] text-white' : 'bg-gray-100 text-gray-400' }}">
                        2</div>
                    <span class="text-[9px] md:text-[10px] mt-1 font-bold uppercase text-gray-400">Verify</span>
                </div>
                <div class="flex-1 h-[2px] mx-1 md:mx-2 bg-gray-100"></div>
                <div class="flex flex-col items-center">
                    <div
                        class="w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center text-[10px] md:text-xs font-bold bg-gray-100 text-gray-400">
                        3</div>
                    <span class="text-[9px] md:text-[10px] mt-1 font-bold uppercase text-gray-400">Reset</span>
                </div>
            </div>

            @if (!$isSent)
                <form wire:submit="sendPasswordResetLink" class="space-y-4 md:space-y-5">
                    <div>
                        <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">Student
                            ID</label>
                        <input wire:model="student_id" type="text" placeholder="e.g. 23-0001" required autofocus
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-[#252525] text-sm">
                        <x-input-error :messages="$errors->get('student_id')" class="mt-2 text-red-600 text-[10px]" />
                    </div>

                    <div>
                        <label
                            class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">Registered
                            Email</label>
                        <input wire:model="email" type="email" placeholder="example@email.com" required
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-[#252525] text-sm">
                        <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-600 text-[10px]" />
                    </div>

                    <button type="submit"
                        class="w-full bg-[#108500] hover:bg-[#0d6b00] text-white font-black py-3 md:py-4 rounded-lg shadow-lg shadow-green-200 transition-all uppercase tracking-widest text-xs md:text-sm">
                        <span wire:loading.remove wire:target="sendPasswordResetLink">Send Reset Link</span>
                        <span wire:loading wire:target="sendPasswordResetLink">Sending...</span>
                    </button>
                </form>
            @else
                <div class="text-center py-4">
                    <div
                        class="w-16 h-16 md:w-20 md:h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4 md:mb-6">
                        <svg class="w-8 h-8 md:w-10 md:h-10 text-[#108500]" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-lg md:text-xl font-bold text-[#252525] mb-2">Check your Email</h3>
                    <p class="text-xs md:text-sm text-gray-500 mb-4 md:mb-6 px-4">
                        We've sent a password reset link to <br><b class="text-[#108500]">{{ $email }}</b>
                    </p>

                    <button type="button" wire:click="$set('isSent', false)"
                        class="text-[10px] md:text-xs font-black text-[#108500] uppercase tracking-widest hover:underline">
                        Didn't receive it? Try again
                    </button>
                </div>
            @endif

            <div class="mt-6 md:mt-8 pt-4 md:pt-6 border-t border-gray-50 text-center">
                <a href="{{ url('/') }}" wire:navigate
                    class="text-[10px] md:text-xs font-bold text-gray-400 uppercase tracking-widest hover:text-[#108500] inline-flex items-center transition-colors">
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

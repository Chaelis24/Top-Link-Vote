<?php

use Illuminate\Support\Facades\Password;
use App\Traits\ChecksMaintenance;
use Livewire\Attributes\{Layout, Title};
use Livewire\Volt\Component;
use App\Models\{Setting, Student};
use Illuminate\Support\Facades\Cache;

new #[Layout('layouts.guest')] #[Title('Forgot Password')] class extends Component {
    use ChecksMaintenance;

    public string $email = '';
    public string $student_id = '';
    public bool $isSent = false;

    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'student_id' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
        ]);

        $student = Student::where('student_id', $this->student_id)->whereHas('user', fn($q) => $q->where('email', $this->email))->first();

        if (!$student) {
            session()->flash('error', 'The provided details do not match our records.');
            return;
        }

        if ($student->user->email_verified_at === null) {
            session()->flash('error', 'Please verify your account first before resetting your password.');
            return;
        }

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status !== Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->isSent = true;
        session()->flash('status', 'Reset link sent!');
    }
}; ?>

<div class="fixed inset-0 z-[9999] overflow-y-auto bg-white flex items-center justify-center p-4 m-0 w-full h-full">
    <div class="absolute inset-0 bg-white"></div>

    <div
        class="relative z-10 max-w-4xl w-full bg-white shadow-2xl rounded-2xl overflow-hidden border border-gray-100 mx-2 md:mx-0">

        @if ($isMaintenance)
            <div class="bg-gray-50 py-16 px-6 w-full flex flex-col justify-center items-center min-h-[400px]">
                <div class="p-8 bg-gray-50 inline-block rounded-xl">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
                        class="overflow-visible w-24 h-24 md:w-44 md:h-44">
                        <path class="gear-big fill-gray-600"
                            d="M11.5,2C11.2,2 11,2.2 11,2.5V3.3C10.1,3.4 9.3,3.8 8.5,4.3L7.9,3.7C7.7,3.5 7.4,3.5 7.2,3.7L5.8,5.1C5.6,5.3 5.6,5.6 5.8,5.8L6.4,6.4C5.9,7.2 5.5,8 5.4,8.9H4.5C4.2,8.9 4,9.1 4,9.4V11.4C4,11.7 4.2,11.9 4.5,11.9H5.4C5.5,12.8 5.9,13.6 6.4,14.4L5.8,15C5.6,15.2 5.6,15.5 5.8,15.7L7.2,17.1C7.4,17.3 7.7,17.3 7.9,17.1L8.5,16.5C9.3,17 10.1,17.4 11,17.5V18.4C11,18.7 11.2,18.9 11.5,18.9H13.5C13.8,18.9 14,18.7 14,18.4V17.5C14.9,17.4 15.7,17 16.5,16.5L17.1,17.1C17.3,17.3 17.6,17.3 17.8,17.1L19.2,15.7C19.4,15.5 19.4,15.2 19.2,15L18.6,14.4C19.1,13.6 19.5,12.8 19.6,11.9H20.5C20.8,11.9 21,11.7 21,11.4V9.4C21,9.1 20.8,8.9 20.5,8.9H19.6C19.5,8 19.1,7.2 18.6,6.4L19.2,5.8C19.4,5.6 19.4,5.3 19.2,5.1L17.8,3.7C17.6,3.5 17.3,3.5 17.1,3.7L16.5,4.3C15.7,3.8 14.9,3.4 14,3.3V2.5C14,2.2 13.8,2 13.5,2H11.5M12.5,7A3.5,3.5 0 0,1 16,10.5A3.5,3.5 0 0,1 12.5,14A3.5,3.5 0 0,1 9,10.5A3.5,3.5 0 0,1 12.5,7Z" />
                        <path class="gear-small fill-yellow-500"
                            d="M5.5,15.5C5.2,15.5 5,15.7 5,16V16.3C4.5,16.4 4,16.6 3.6,16.9L3.3,16.6C3.1,16.4 2.8,16.4 2.6,16.6L2.1,17.1C1.9,17.3 1.9,17.6 2.1,17.8L2.4,18.1C2.1,18.5 1.9,19 1.8,19.4H1.5C1.2,19.4 1,19.6 1,19.9V20.6C1,20.9 1.2,21.1 1.5,21.1H1.8C1.9,21.6 2.1,22 2.4,22.4L2.1,22.7C1.9,22.9 1.9,23.2 2.1,23.4L2.6,23.9C2.8,24.1 3.1,24.1 3.3,23.9L3.6,23.6C4,23.9 4.5,24.1 5,24.2V24.5C5,24.8 5.2,25 5.5,25H6.2C6.5,25 6.7,24.8 6.7,24.5V24.2C7.2,24.1 7.7,23.9 8.1,23.6L8.4,23.9C8.6,24.1 8.9,24.1 9.1,23.9L9.6,23.4C9.8,23.2 9.8,22.9 9.6,22.7L9.3,22.4C9.6,22 9.8,21.6 9.9,21.1H10.2C10.5,21.1 10.7,20.9 10.7,20.6V19.9C10.7,19.6 10.5,19.4 10.2,19.4H9.9C9.8,19 9.6,18.5 9.3,18.1L9.6,17.8C9.8,17.6 9.8,17.3 9.6,17.1L9.1,16.6C8.9,16.4 8.6,16.4 8.4,16.6L8.1,16.9C7.7,16.6 7.2,16.4 6.7,16.3V16C6.7,15.7 6.5,15.5 6.2,15.5H5.5M5.8,18.5A1.75,1.75 0 0,1 7.6,20.25A1.75,1.75 0 0,1 5.8,22A1.75,1.75 0 0,1 4.1,20.25A1.75,1.75 0 0,1 5.8,18.5Z" />
                    </svg>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-center text-gray-700 mb-4 tracking-tight">
                    Site is under maintenance
                </h1>
                <p class="text-center text-gray-500 text-sm md:text-xl max-w-md">
                    We're working hard to improve the user experience. Stay tuned!
                </p>
            </div>
        @else
            <div class="flex flex-col md:flex-row">
                <div
                    class="md:w-1/2 bg-[linear-gradient(115deg,#0dff00,#068a08,#010d05)] p-2 md:p-12 text-white flex flex-col justify-center relative overflow-hidden min-h-[160px] md:min-h-[450px]">
                    <div class="absolute -top-24 -left-24 w-64 h-64 bg-black/10 rounded-full"></div>
                    <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-white/20 rounded-full"></div>

                    <div class="relative z-10 flex flex-col items-center text-center">
                        <div class="mb-2 md:mb-4">
                            <img src="{{ asset('images/logo.png') }}" alt="Top Link Logo" class="w-24 md:w-40 h-auto">
                        </div>
                        <h2
                            class="text-lg md:text-2xl font-extrabold uppercase mb-2 md:mb-3 tracking-tight text-white drop-shadow-md">
                            Recovery
                        </h2>
                        <p class="block text-white/90 leading-relaxed text-xs md:text-sm font-medium">
                            Password recovery is quick and easy.
                            Please verify your information below to securely access your voting account.
                        </p>
                    </div>
                </div>

                <div class="md:w-1/2 p-5 md:p-10 flex flex-col justify-center bg-white">
                    <div class="mb-3 md:mb-5 text-start md:text-left">
                        <h2 class="text-lg md:text-2xl font-bold text-[#252525] mb-1 md:mb-2 tracking-tighter">Forgot
                            Password?
                        </h2>
                        <p class="text-gray-500 pb-1 pb-md-0 text-xs md:text-sm">Account Recovery Process</p>
                    </div>

                    <div class="flex items-center justify-between mb-3 md:mb-4 px-2">
                        <div class="flex flex-col items-center">
                            <div
                                class="w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center text-[10px] md:text-xs font-bold {{ !$isSent ? 'bg-[#108500] text-white' : 'bg-green-100 text-[#108500]' }}">
                                1</div>
                            <span
                                class="text-[9px] md:text-[10px] mt-1 font-bold uppercase text-gray-400">Details</span>
                        </div>
                        <div
                            class="flex-1 h-[2px] mx-1 md:mx-2 mb-4 mb-md-0 {{ $isSent ? 'bg-[#108500]' : 'bg-gray-100' }}">
                        </div>
                        <div class="flex flex-col items-center">
                            <div
                                class="w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center text-[10px] md:text-xs font-bold {{ $isSent ? 'bg-[#108500] text-white' : 'bg-gray-100 text-gray-400' }}">
                                2</div>
                            <span class="text-[9px] md:text-[10px] mt-1 font-bold uppercase text-gray-400">Verify</span>
                        </div>
                        <div class="flex-1 h-[2px] mx-1 md:mx-2 bg-gray-100 mb-4 mb-md-0"></div>
                        <div class="flex flex-col items-center">
                            <div
                                class="w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center text-[10px] md:text-xs font-bold bg-gray-100 text-gray-400">
                                3</div>
                            <span class="text-[9px] md:text-[10px] mt-1 font-bold uppercase text-gray-400">Reset</span>
                        </div>
                    </div>

                    @if (session('error'))
                        <div
                            class="mb-4 text-red-600 text-[10px] md:text-[11px] font-bold uppercase p-3 bg-red-50 rounded-lg border border-red-200">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if (!$isSent)
                        <form wire:submit="sendPasswordResetLink" class="space-y-4 md:space-y-5">
                            <div>
                                <label
                                    class="text-[10px] md:text-[11px] font-bold uppercase text-gray-500 mb-1 block ms-1">
                                    Student ID
                                </label>
                                <input wire:model="student_id" type="text" placeholder="e.g. 23-0001" required
                                    autofocus
                                    class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00]/30 focus:border-[#108500] outline-none transition-all text-[#252525] text-sm">
                                <x-input-error :messages="$errors->get('student_id')" class="mt-2 text-red-600 text-[10px]" />
                            </div>

                            <div>
                                <label
                                    class="text-[10px] md:text-[11px] font-bold uppercase text-gray-500 mb-1 block ms-1">
                                    Registered Email
                                </label>
                                <input wire:model="email" type="email" placeholder="example@gmail.com" required
                                    class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00]/30 focus:border-[#108500] outline-none transition-all text-[#252525] text-sm">
                                <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-600 text-[10px]" />
                            </div>

                            <button type="submit"
                                class="w-full bg-[#108500] hover:bg-[#0d6b00] text-white font-semibold py-3 rounded-lg transition-all uppercase tracking-widest text-xs btn-loader">
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
                            <h3 class="text-md md:text-lg font-bold text-[#252525] mb-2">Check your Email</h3>
                            <p class="text-xs md:text-sm text-gray-500 mb-4 md:mb-6 px-4">
                                We've sent a password reset link to <br><b
                                    class="text-[#108500]">{{ $email }}</b>
                            </p>

                            <button type="button" wire:click="$set('isSent', false)"
                                class="text-[10px] md:text-xs font-black text-[#108500] uppercase tracking-widest hover:underline">
                                Didn't receive it? Try again
                            </button>
                        </div>
                    @endif

                    <div class="mt-0 md:mt-3 pt-4 md:pt-6 border-t border-gray-50 text-center">
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
    @endif
</div>

<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->string('email');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset($this->only('email', 'password', 'password_confirmation', 'token'), function ($user) {
            $user
                ->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])
                ->save();

            event(new PasswordReset($user));
        });

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));
            return;
        }

        Session::flash('status', 'Your password has been reset successfully!');
        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="min-h-screen w-full flex items-center justify-center bg-white p-4 m-0 font-['Raleway']">
    <div
        class="max-w-4xl w-full bg-white shadow-2xl rounded-2xl overflow-hidden flex flex-col md:flex-row border border-gray-100 mx-2 md:mx-0">

        <div
            class="md:w-1/2 bg-gradient-to-br from-[#108500] to-[#9cff00] p-6 md:p-12 text-white flex flex-col justify-center relative overflow-hidden min-h-[160px] md:min-h-[450px]">
            <div class="absolute -top-24 -left-24 w-64 h-64 bg-black/10 rounded-full"></div>
            <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-white/20 rounded-full"></div>

            <div class="relative z-10 flex flex-col items-center text-center">
                <div class="mb-4 md:mb-8">
                    <img src="{{ asset('images/logo.png') }}" alt="Top Link Logo" class="w-24 md:w-48 h-auto">
                </div>

                <h2
                    class="text-xl md:text-3xl font-extrabold uppercase mb-2 md:mb-4 tracking-tight text-white drop-shadow-md">
                    Security
                </h2>

                <p class="hidden md:block text-white leading-relaxed text-sm font-bold">
                    Create a strong password to protect your account.
                    Your security is our priority as we ensure a fair and safe voting process.
                </p>
            </div>
        </div>

        <div class="md:w-1/2 p-6 md:p-10 flex flex-col justify-center bg-white">
            <div class="mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-[#252525] mb-1 md:mb-2 tracking-tighter">New Password
                </h2>
                <p class="text-gray-500 text-xs md:text-sm">Final step of account recovery</p>
            </div>

            <div class="flex items-center justify-between mb-6 md:mb-8 px-2">
                <div class="flex flex-col items-center">
                    <div
                        class="w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center text-[10px] font-bold bg-green-100 text-[#108500]">
                        <svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" />
                        </svg>
                    </div>
                    <span class="text-[9px] md:text-[10px] mt-1 font-bold uppercase text-gray-400">Details</span>
                </div>
                <div class="flex-1 h-[2px] mx-1 md:mx-2 bg-[#108500]"></div>
                <div class="flex flex-col items-center">
                    <div
                        class="w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center text-[10px] font-bold bg-green-100 text-[#108500]">
                        <svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" />
                        </svg>
                    </div>
                    <span class="text-[9px] md:text-[10px] mt-1 font-bold uppercase text-gray-400">Verify</span>
                </div>
                <div class="flex-1 h-[2px] mx-1 md:mx-2 bg-[#108500]"></div>
                <div class="flex flex-col items-center">
                    <div
                        class="w-7 h-7 md:w-8 md:h-8 rounded-full flex items-center justify-center text-[10px] font-bold bg-[#108500] text-white">
                        3</div>
                    <span class="text-[9px] md:text-[10px] mt-1 font-bold uppercase text-[#108500]">Reset</span>
                </div>
            </div>

            <form wire:submit="resetPassword" class="space-y-4 md:space-y-5">
                <div>
                    <label class="text-[10px] md:text-xs font-bold uppercase text-gray-400 mb-1 block ms-1">Resetting
                        for Email</label>
                    <input wire:model="email" type="email" readonly
                        class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-100 bg-gray-50 text-gray-400 outline-none cursor-not-allowed text-xs md:text-sm font-semibold">
                    <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-600" />
                </div>

                <div x-data="{ show: false }" class="relative">
                    <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">New
                        Password</label>
                    <input :type="show ? 'text' : 'password'" wire:model="password" placeholder="Minimum 8 characters"
                        required autofocus
                        class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-[#252525] text-sm">
                    <button type="button" @click="show = !show"
                        class="absolute right-3 top-[32px] md:top-[38px] text-[9px] md:text-[10px] font-black text-[#108500] uppercase tracking-widest">
                        <span x-text="show ? 'Hide' : 'Show'"></span>
                    </button>
                    <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-600" />
                </div>

                <div x-data="{ show: false }" class="relative">
                    <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">Confirm New
                        Password</label>
                    <input :type="show ? 'text' : 'password'" wire:model="password_confirmation"
                        placeholder="Repeat password" required
                        class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-[#252525] text-sm">
                    <button type="button" @click="show = !show"
                        class="absolute right-3 top-[32px] md:top-[38px] text-[9px] md:text-[10px] font-black text-[#108500] uppercase tracking-widest">
                        <span x-text="show ? 'Hide' : 'Show'"></span>
                    </button>
                </div>

                <button type="submit"
                    class="w-full bg-[#108500] hover:bg-[#0d6b00] text-white font-black py-3 md:py-4 rounded-lg shadow-lg shadow-green-200 transition-all uppercase tracking-widest text-xs md:text-sm">
                    <span wire:loading.remove>Update Password</span>
                    <span wire:loading>Processing...</span>
                </button>
            </form>

            <div class="mt-6 md:mt-8 text-center">
                <a href="{{ route('login') }}" wire:navigate
                    class="text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-widest hover:text-[#108500] inline-flex items-center transition-colors">
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

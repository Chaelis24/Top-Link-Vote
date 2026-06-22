<?php

use Illuminate\Support\Facades\{Hash, Password, Session};
use Illuminate\Auth\Events\PasswordReset;
use Livewire\Attributes\{Layout, Locked};
use Illuminate\Validation\Rules;
use Livewire\Volt\Component;
use Illuminate\Support\Str;

/**
 * Admin Reset Password page.
 *
 * Handles the password-reset form after the admin clicks
 * the link received via email.  Validates the token,
 * updates the password, and redirects to the login page.
 */
new #[Layout('layouts.guest')] class extends Component {
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Seed the form with the reset token and email from the URL.
     *
     * @param  string  $token
     * @return void
     */
    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    /**
     * Validate the token and password, then persist the new password.
     *
     * @return void
     */
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
            session()->flash('error', __($status));
            return;
        }

        Session::flash('status', 'Your password has been reset successfully!');
        $this->redirectRoute('admin.login', navigate: true);
    }
}; ?>

{{-- Full-screen centered container for the reset-password form --}}
<div
    class="fixed inset-0 z-[9999] overflow-y-auto bg-transparent flex items-center justify-center p-4 m-0 w-full h-full">
    {{-- Background image with overlay --}}
    <div class="fixed inset-0 bg-cover bg-center bg-no-repeat"
        style="background-image: url('{{ asset('images/bg.jpeg') }}');"></div>
    <div class="fixed inset-0 bg-black/40"></div>

    <div class="relative z-10 max-w-4xl w-full bg-white shadow-2xl rounded-2xl overflow-hidden mx-2 md:mx-0">
        <div class="flex flex-col md:flex-row">
            {{-- Branding panel (left) --}}
            <div
                class="md:w-1/2 bg-gradient-to-br from-[#1e3a8a] to-[#3b82f6] p-2 md:p-12 text-white flex flex-col justify-center relative overflow-hidden min-h-[180px] md:min-h-[450px]">
                <div class="absolute -top-24 -left-24 w-64 h-64 bg-white/10 rounded-full"></div>
                <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-black/10 rounded-full"></div>

                <div class="relative z-10 flex flex-col items-center text-center">
                    <div class="mb-2 md:mb-4">
                        <img src="{{ asset('images/logo.png') }}" alt="Top Link Logo" class="w-24 md:w-40 h-auto">
                    </div>

                    <h2
                        class="text-lg md:text-2xl font-extrabold uppercase mb-2 md:mb-3 tracking-tight text-white drop-shadow-md">
                        Set New Password
                    </h2>

                    <p class="block text-white/90 leading-relaxed text-xs md:text-sm font-medium">
                        Please provide your new credentials <br> to regain administrator access.
                    </p>
                </div>
            </div>

            {{-- Form panel (right) --}}
            <div class="md:w-1/2 p-6 md:p-8 flex flex-col justify-center bg-white">
                <div class="mb-3 md:mb-6">
                    <div
                        class="inline-block px-3 py-2 rounded-full bg-blue-50 text-[#1e3a8a] text-[9px] font-black uppercase tracking-widest mb-3">
                        Identity Confirmed
                    </div>
                    <h2 class="text-lg md:text-xl font-bold text-[#252525] mb-1 md:mb-2 tracking-tighter">New Password
                    </h2>
                    <p class="text-gray-500 text-xs md:text-sm">Finalize your account recovery below.</p>
                </div>

                {{-- Successful, Error and Warning Messages --}}
                <x-session-flash></x-session-flash>

                <form wire:submit="resetPassword" class="space-y-4 md:space-y-5">
                    {{-- Read-only email field --}}
                    <div>
                        <label class="text-[10px] font-bold uppercase text-gray-500 mb-1 block ms-1">Registered
                            Address</label>
                        <input wire:model="email" type="email" readonly
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-100 bg-gray-50 text-gray-400 text-sm outline-none cursor-not-allowed">
                    </div>

                    {{-- New password field with show/hide toggle --}}
                    <div x-data="{ show: false }" class="relative">
                        <label class="text-[10px] font-bold uppercase text-gray-500 mb-1 block ms-1">New
                            Password</label>
                        <input :type="show ? 'text' : 'password'" wire:model="password"
                            placeholder="At least 8 characters" required autofocus
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-100 focus:border-[#1e3a8a] outline-none transition-all text-sm text-[#1e293b]">
                        <button type="button" @click="show = !show" aria-label="Toggle password visibility"
                            class="absolute right-3 top-1/2 -translate-y-1/2 {{ $errors->has('password') ? 'text-red-500' : 'text-gray-400 hover:text-[#3b82f6]' }} transition-colors">
                            <i :class="show ? 'bi bi-eye-fill' : 'bi bi-eye-slash-fill'" class="text-md"></i>
                        </button>
                    </div>

                    {{-- Confirm password field with show/hide toggle --}}
                    <div x-data="{ show: false }" class="relative">
                        <label class="text-[10px] font-bold uppercase text-gray-500 mb-1 block ms-1">Confirm
                            Password</label>
                        <input :type="show ? 'text' : 'password'" wire:model="password_confirmation"
                            placeholder="Re-type new password" required
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-100 focus:border-[#1e3a8a] outline-none transition-all text-sm text-[#1e293b]">
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-red-600 text-[10px]" />
                        <button type="button" @click="show = !show" aria-label="Toggle password visibility"
                            class="absolute right-3 top-1/2 -translate-y-1/2 {{ $errors->has('password') ? 'text-red-500' : 'text-gray-400 hover:text-[#3b82f6]' }} transition-colors">
                            <i :class="show ? 'bi bi-eye-fill' : 'bi bi-eye-slash-fill'" class="text-md"></i>
                        </button>
                    </div>

                    {{-- Submit button with loading state --}}
                    <button type="submit"
                        class="w-full bg-[#1e3a8a] hover:bg-[#1e293b] text-white font-semibold py-3 rounded-lg transition-all uppercase tracking-widest text-xs md:text-sm mt-2">
                        <span wire:loading.remove>Update Password</span>
                        <span wire:loading>Updating...</span>
                    </button>
                </form>

                {{-- Cancel and return to login --}}
                <div class="mt-0 md:mt-4 pt-4 md:pt-6 border-t border-gray-50 text-center">
                    <a href="{{ route('admin.login') }}" wire:navigate
                        class="text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-[#1e3a8a] inline-flex items-center transition-colors">
                        <svg class="w-4 h-4 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Cancel and Return
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Admin Forgot Password page.
 *
 * Allows administrators to request a password-reset link
 * sent to their registered email address via Laravel's
 * Password broker.
 */
new #[Layout('layouts.guest')] class extends Component {
    public string $email = '';

    /**
     * Validate the email and send a password-reset link.
     *
     * @return void
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate(['email' => ['required', 'email']]);

        $status = Password::broker()->sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('status', __($status));
        } else {
            $this->addError('email', __($status));
        }
    }
}; ?>

{{-- Full-screen centered container for the forgot-password form --}}
<div class="fixed inset-0 z-[9999] overflow-y-auto bg-white flex items-center justify-center p-4 m-0 w-full h-full">
    <div class="absolute inset-0 bg-white"></div>

    <div
        class="relative z-10 max-w-4xl w-full bg-white shadow-2xl rounded-2xl overflow-hidden border border-gray-100 mx-2 md:mx-0">
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
                        Access Recovery
                    </h2>

                    <p class="block text-white/90 leading-relaxed text-xs md:text-sm font-medium">
                        Secure password reset for <br> Election Management Administrators.
                    </p>
                </div>
            </div>

            {{-- Form panel (right) --}}
            <div class="md:w-1/2 p-6 md:p-8 flex flex-col justify-center bg-white">
                <div class="mb-3 md:mb-6">
                    <div
                        class="inline-block px-3 py-2 rounded-full bg-blue-50 text-[#1e3a8a] text-[9px] font-black uppercase tracking-widest mb-3">
                        Security Verification
                    </div>
                    <h2 class="text-lg md:text-xl font-bold text-[#252525] mb-1 md:mb-2 tracking-tighter">Reset Link
                    </h2>
                    <p class="text-gray-500 text-xs md:text-sm">Enter your email to receive instructions.</p>
                </div>

                {{-- Status / success flash message --}}
                @if (session('status'))
                    <div
                        class="mb-2 text-green-600 text-[10px] font-bold uppercase p-3 bg-green-50 rounded-lg border border-green-100">
                        {{ session('status') }}
                    </div>
                @endif

                <form wire:submit="sendPasswordResetLink" class="space-y-4 md:space-y-6">
                    <div>
                        <label class="text-[10px] font-bold uppercase text-gray-500 mb-1 block ms-1">Admin Email</label>
                        <input wire:model="email" type="email" placeholder="admin@gmail.com" required autofocus
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-100 focus:border-[#1e3a8a] outline-none transition-all text-sm text-[#1e293b]">
                        <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-600 text-[10px]" />
                    </div>

                    {{-- Submit button with loading state --}}
                    <button type="submit"
                        class="w-full bg-[#1e3a8a] hover:bg-[#1e293b] text-white font-semibold py-3 rounded-lg transition-all uppercase tracking-widest text-xs md:text-sm">
                        <span wire:loading.remove>Send Reset Email</span>
                        <span wire:loading>Processing...</span>
                    </button>
                </form>

                {{-- Back to login link --}}
                <div class="mt-0 md:mt-3 pt-4 md:pt-6 border-t border-gray-50 text-center">
                    <a href="{{ route('admin.login') }}" wire:navigate
                        class="text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-[#1e3a8a] inline-flex items-center transition-colors">
                        <svg class="w-4 h-4 me-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Admin Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

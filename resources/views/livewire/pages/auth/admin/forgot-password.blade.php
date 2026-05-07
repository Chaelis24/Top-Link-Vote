<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public string $email = '';

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

<div class="min-h-screen w-full flex items-center justify-center bg-[#f8fafc] p-4 m-0 font-['Raleway']">
    <div
        class="max-w-3xl w-full bg-white shadow-2xl rounded-2xl overflow-hidden flex flex-col md:flex-row border border-gray-100">

        <div
            class="md:w-1/2 bg-gradient-to-br from-[#1e3a8a] to-[#3b82f6] p-6 md:p-12 text-white flex flex-col justify-center relative overflow-hidden min-h-[180px] md:min-h-[450px]">
            <div class="absolute -top-24 -left-24 w-64 h-64 bg-white/10 rounded-full"></div>
            <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-black/10 rounded-full"></div>

            <div class="relative z-10 flex flex-col items-center text-center">
                <div class="mb-4 md:mb-8">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo" class="w-24 md:w-48 h-auto">
                </div>
                <h2 class="text-xl md:text-3xl font-extrabold uppercase mb-1 md:mb-2 tracking-tighter text-white">
                    Access Recovery
                </h2>
                <div class="hidden md:block h-1 w-12 bg-white/30 mb-6 rounded-full mx-auto"></div>
                <p class="hidden md:block text-white/90 leading-relaxed text-sm font-medium">
                    Secure password reset for <br> Election Management Administrators.
                </p>
            </div>
        </div>

        <div class="md:w-1/2 p-6 md:p-10 flex flex-col justify-center bg-white">
            <div class="mb-6 md:mb-8 text-center md:text-left">
                <div
                    class="inline-block px-3 py-1 rounded-full bg-blue-50 text-[#1e3a8a] text-[9px] font-black uppercase tracking-widest mb-3">
                    Security Verification
                </div>
                <h2 class="text-2xl md:text-3xl font-bold text-[#1e293b] mb-1 tracking-tighter">Reset Link</h2>
                <p class="text-gray-500 text-xs md:text-sm">Enter your email to receive instructions.</p>
            </div>

            @if (session('status'))
                <div
                    class="mb-4 text-green-600 text-[10px] font-bold uppercase p-3 bg-green-50 rounded-lg border border-green-100">
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

                <button type="submit"
                    class="w-full bg-[#1e3a8a] hover:bg-[#1e293b] text-white font-black py-3 md:py-4 rounded-lg shadow-lg shadow-blue-100 transition-all uppercase tracking-widest text-xs md:text-sm">
                    <span wire:loading.remove>Send Reset Email</span>
                    <span wire:loading>Processing...</span>
                </button>
            </form>

            <div class="mt-6 md:mt-8 pt-4 md:pt-6 border-t border-gray-50 text-center">
                <a href="{{ route('admin.login') }}" wire:navigate
                    class="text-[10px] font-bold text-gray-400 uppercase tracking-widest hover:text-[#1e3a8a] inline-flex items-center transition-colors">
                    Back to Admin Login
                </a>
            </div>
        </div>
    </div>
</div>

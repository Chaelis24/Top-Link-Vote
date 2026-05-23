<?php

use Illuminate\Support\Facades\{Hash, Password, Session};
use Illuminate\Auth\Events\PasswordReset;
use Livewire\Attributes\{Layout, Locked};
use Illuminate\Validation\Rules;
use Livewire\Volt\Component;
use Illuminate\Support\Str;

new #[Layout('layouts.guest')] class extends Component {
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
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
            $this->dispatch('swal:modal', [
                'icon' => 'error',
                'title' => 'Error',
                'text' => __($status),
            ]);
            return;
        }

        Session::flash('status', 'Your password has been reset successfully!');
        $this->redirectRoute('admin.login', navigate: true);
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
                    Set New Password
                </h2>
                <p class="block text-white/90 leading-relaxed text-xs text-md-sm font-medium">
                    Please provide your new credentials <br> to regain administrator access.
                </p>
            </div>
        </div>

        <div class="md:w-1/2 p-6 md:p-8 flex flex-col justify-center bg-white">
            <div class="mb-4 md:mb-8 text-start">
                <div
                    class="inline-block px-3 py-1 rounded-full bg-blue-50 text-[#1e3a8a] text-[9px] font-black uppercase tracking-widest mb-3">
                    Identity Confirmed
                </div>
                <h2 class="text-2xl md:text-3xl font-bold text-[#1e293b] mb-1 tracking-tighter">New Password</h2>
                <p class="text-gray-500 text-xs md:text-sm">Finalize your account recovery below.</p>
            </div>

            <form wire:submit="resetPassword" class="space-y-4 md:space-y-5">
                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 mb-1 block ms-1">Registered
                        Address</label>
                    <input wire:model="email" type="email" readonly
                        class="w-full px-4 py-2.5 rounded-lg border border-gray-100 bg-gray-50 text-gray-400 text-sm outline-none cursor-not-allowed">
                </div>

                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 mb-1 block ms-1">New Password</label>
                    <input wire:model="password" type="password" placeholder="At least 8 characters" required autofocus
                        class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-100 focus:border-[#1e3a8a] outline-none transition-all text-sm text-[#1e293b]">
                    <x-input-error :messages="$errors->get('password')" class="mt-1 text-red-600 text-[10px]" />
                </div>

                <div>
                    <label class="text-[10px] font-bold uppercase text-gray-500 mb-1 block ms-1">Confirm
                        Password</label>
                    <input wire:model="password_confirmation" type="password" placeholder="Re-type new password"
                        required
                        class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-blue-100 focus:border-[#1e3a8a] outline-none transition-all text-sm text-[#1e293b]">
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-red-600 text-[10px]" />
                </div>

                <button type="submit"
                    class="w-full bg-[#1e3a8a] hover:bg-[#1e293b] text-white font-black py-3 rounded-lg transition-all uppercase tracking-widest text-xs md:text-sm mt-2">
                    <span wire:loading.remove>Update Password</span>
                    <span wire:loading>Updating...</span>
                </button>
            </form>

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

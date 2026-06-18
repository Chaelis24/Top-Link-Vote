<?php

use App\Livewire\Forms\AdminLoginForm;
use Illuminate\Support\Facades\{Auth, DB, Password, Session};
use App\Events\UserLoggedInElsewhere;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

/**
 * Admin login page component.
 *
 * Handles admin authentication with role verification,
 * concurrent session invalidation, and password reset requests.
 */
new #[Layout('layouts.guest')] class extends Component {
    public AdminLoginForm $form;

    /**
     * Authenticate the admin user.
     *
     * Validates credentials, ensures the user has the 'admin' role,
     * invalidates existing sessions to prevent concurrent logins,
     * and broadcasts an event if other sessions were revoked.
     */
    public function login(): void
    {
        try {
            $user = $this->form->validateCredentials();

            if (!$user->roles()->where('name', 'admin')->exists()) {
                session()->flash('error', 'Unauthorized access. This portal is for administrators only.');
                return;
            }

            DB::transaction(function () use ($user) {
                DB::table('users')->where('id', $user->id)->lockForUpdate()->first();

                $oldSessionIds = DB::table('sessions')->where('user_id', $user->id)->pluck('id');

                DB::table('sessions')->where('user_id', $user->id)->delete();

                Auth::login($user, $this->form->remember);
                Session::regenerate();

                if ($oldSessionIds->isNotEmpty()) {
                    broadcast(new UserLoggedInElsewhere($user->id));
                }
            });

            $this->redirectIntended(route('admin.dashboard'), navigate: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->form->password = '';
            session()->flash('error', 'Invalid credentials.');
        }
    }

    /**
     * Send a password reset link to the admin's email.
     *
     * Validates the email and dispatches a password reset
     * notification through the default broker.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate(['form.email' => 'required|email']);
        $status = Password::broker()->sendResetLink(['email' => $this->form->email]);

        if ($status === Password::RESET_LINK_SENT) {
            session()->flash('status', __($status));
        } else {
            session()->flash('error', __($status));
        }
    }
}; ?>
{{-- Admin Login Page — provides the authentication interface for administrators. --}}

<div class="fixed inset-0 z-[9999] overflow-y-auto bg-white flex items-center justify-center p-4 m-0 w-full h-full">
    {{-- Background overlay --}}
    <div class="absolute inset-0 bg-white"></div>

    {{-- Main card container with brand panel and login form side-by-side on larger screens --}}
    <div
        class="relative z-10 max-w-4xl w-full bg-white shadow-2xl rounded-2xl overflow-hidden border border-gray-100 mx-2 md:mx-0">
        <div class="flex flex-col md:flex-row">
            {{-- Branding panel — displays the logo, portal title, and description --}}
            <div
                class="md:w-1/2 bg-gradient-to-br from-[#1e3a8a] to-[#3b82f6] p-2 md:p-12 text-white flex flex-col justify-center relative overflow-hidden min-h-[180px] md:min-h-[450px]">
                <div class="absolute -top-24 -left-24 w-64 h-64 bg-white/10 rounded-full"></div>
                <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-black/10 rounded-full"></div>

                <div class="relative z-10 flex flex-col items-center text-center">
                    {{-- Application logo --}}
                    <div class="mb-2 md:mb-4">
                        <img src="{{ asset('images/logo.png') }}" alt="Top Link Logo" class="w-24 md:w-40 h-auto">
                    </div>

                    <h2
                        class="text-lg md:text-2xl font-extrabold uppercase mb-2 md:mb-3 tracking-tight text-white drop-shadow-md">
                        Admin Portal
                    </h2>

                    <p class="block text-white/90 leading-relaxed text-xs md:text-sm font-medium">
                        Election Management & Real-time Monitor
                        and secure the electoral process.
                    </p>
                </div>
            </div>

            {{-- Login form panel --}}
            <div class="md:w-1/2 p-5 md:p-10 flex flex-col justify-center bg-white">
                <div class="mb-3 md:mb-6">
                    <div
                        class="inline-block px-3 py-2 rounded-full bg-blue-50 text-[#1e3a8a] text-[9px] font-black uppercase tracking-widest mb-3">
                        Secure Admin Access
                    </div>
                    <h2 class="text-lg md:text-xl font-bold text-[#252525] mb-1 md:mb-2 tracking-tighter">System Login
                    </h2>
                    <p class="text-gray-500 text-xs md:text-sm">Enter your credentials.</p>
                </div>

                {{--Successful, Error and Warning Messages--}}
                <x-session-flash></x-session-flash>

                <form wire:submit="login" class="space-y-4 md:space-y-5">
                    {{-- Email input field --}}
                    <div>
                        <label class="text-[10px] font-bold uppercase text-gray-500 mb-1 block ms-1">Admin Email</label>
                        <input wire:model="form.email" type="email" placeholder="admin@gmail.com" required autofocus
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border outline-none transition-all text-sm text-[#1e293b] focus:ring-2">
                    </div>

                    {{-- Password input field with visibility toggle --}}
                    <div x-data="{ show: false }" class="relative">
                        <label class="text-[10px] font-bold uppercase text-gray-500 mb-1 block ms-1">Admin
                            Password</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" wire:model="form.password" placeholder="••••••••"
                                required
                                class="w-full px-4 py-2.5 md:py-3 rounded-lg border outline-none transition-all text-sm text-[#1e293b] focus:ring-2">
                            <button type="button" @click="show = !show" aria-label="Toggle password visibility"
                                class="absolute right-3 top-1/2 -translate-y-1/2 {{ $errors->has('form.password') ? 'text-red-500' : 'text-gray-400 hover:text-[#3b82f6]' }} transition-colors">
                                <i :class="show ? 'bi bi-eye-fill' : 'bi bi-eye-slash-fill'" class="text-md"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Remember me checkbox and forgot password link --}}
                    <div
                        class="flex items-center justify-between text-[9px] md:text-[9px] font-bold uppercase tracking-tighter">
                        <label class="flex items-center text-[#1e293b] cursor-pointer">
                            <input type="checkbox" wire:model="form.remember"
                                class="rounded border-gray-300 text-[#1e3a8a] shadow-sm focus:ring-[#1e3a8a]">
                            <span class="ms-2">Maintain Session</span>
                        </label>
                        <a href="{{ route('admin.forgot-password') }}" wire:navigate
                            class="text-gray-500 hover:text-[#1e3a8a]">
                            Forgot Password?
                        </a>
                    </div>

                    {{-- Submit button with loading state --}}
                    <button type="submit"
                        class="w-full bg-[#1e3a8a] hover:bg-[#1e293b] text-white font-semibold py-3 rounded-lg transition-all uppercase tracking-widest text-xs md:text-sm">
                        <span wire:loading.remove>Enter</span>
                        <span wire:loading>Authenticating...</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

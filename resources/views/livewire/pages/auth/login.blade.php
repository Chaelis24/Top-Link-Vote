<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\{Session, RateLimiter};
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public LoginForm $form;

    public function login(): void
    {
        $throttleKey = Str::transliterate(Str::lower($this->form->student_id) . '|' . request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('form.student_id', "Too many login attempts. Please try again in $seconds seconds.");
            return;
        }

        try {
            $this->validate();

            $this->form->authenticate();

            RateLimiter::clear($throttleKey);

            auth()->logoutOtherDevices($this->form->password);

            $user = auth()->user();

            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', session()->getId())
                ->delete();

            if ($user->roles()->where('name', 'admin')->exists()) {
                $this->redirectIntended(route('admin.dashboard'));
                return;
            }

            $this->redirectIntended(route('student.dashboard'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            RateLimiter::hit($throttleKey, 60);
            $this->form->password = '';
            $this->dispatch('auth-failed');
            throw $e;
        }
    }
}; ?>

<div class="fixed inset-0 z-[9999] overflow-y-auto bg-white flex items-center justify-center p-4 m-0 w-full h-full">

    <div class="absolute inset-0 bg-white"></div>

    <div
        class="relative z-10 max-w-4xl w-full bg-white shadow-2xl rounded-2xl overflow-hidden flex flex-col md:flex-row border border-gray-100 mx-2 md:mx-0">

        <div
            class="md:w-1/2 bg-[linear-gradient(115deg,#0dff00,#068a08,#010d05)] p-6 md:p-12 text-white flex flex-col justify-center relative overflow-hidden min-h-[180px] md:min-h-[450px]">
            <div class="absolute -top-24 -left-24 w-64 h-64 bg-black/10 rounded-full"></div>
            <div class="absolute -bottom-24 -right-24 w-48 h-48 bg-white/20 rounded-full"></div>

            <div class="relative z-10 flex flex-col items-center text-center">
                <div class="mb-4 md:mb-8">
                    <img src="{{ asset('images/logo.png') }}" alt="Top Link Logo" class="w-28 md:w-48 h-auto">
                </div>

                <h2
                    class="text-xl md:text-3xl font-extrabold uppercase mb-2 md:mb-4 tracking-tight text-white drop-shadow-md">
                    Top Link-Vote
                </h2>

                <p class="hidden md:block text-white leading-relaxed text-sm font-bold">
                    Empowering the student body through a transparent and secure digital ballot.
                    Exercise your right to choose the next generation of student leaders.
                    Your vote, your voice, our future.
                </p>
            </div>
        </div>

        <div class="md:w-1/2 p-6 md:p-10 flex flex-col justify-center bg-white">
            <div class="mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-[#252525] mb-1 md:mb-2 tracking-tighter">Sign in</h2>
                <p class="text-gray-500 text-xs md:text-sm">Please log in with your Student Credentials.</p>
            </div>

            <x-auth-session-status class="mb-4 text-[#108500] font-bold" :status="session('status')" />

            <form wire:submit="login" class="space-y-4 md:space-y-5">
                <div>
                    <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">Student
                        ID</label>
                    <input wire:model="form.student_id" type="text" placeholder="e.g. 23-0001" required autofocus
                        class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-[#252525] bg-white text-sm">
                    <x-input-error :messages="$errors->get('form.student_id')" class="mt-2 text-red-600" />
                </div>

                <div x-data="{ show: false }" class="relative">
                    <label
                        class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">Password</label>
                    <input :type="show ? 'text' : 'password'" wire:model="form.password" placeholder="password" required
                        class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-[#252525] bg-white text-sm">

                    <button type="button" @click="show = !show"
                        class="absolute right-3 top-[32px] md:top-[38px] text-[9px] md:text-[10px] font-black text-[#108500] hover:text-[#0d6b00] uppercase tracking-widest">
                        <span x-text="show ? 'Hide' : 'Show'"></span>
                    </button>
                    <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
                </div>

                <div
                    class="flex items-center justify-between text-[10px] md:text-xs font-bold uppercase tracking-tighter">
                    <label class="flex items-center text-[#252525] cursor-pointer">
                        <input type="checkbox" wire:model="form.remember"
                            class="rounded border-gray-300 text-[#108500] shadow-sm focus:ring-[#9cff00]">
                        <span class="ms-2">Remember me</span>
                    </label>
                    @if (Route::has('forgot-password'))
                        <a href="{{ route('forgot-password') }}" wire:navigate
                            class="text-gray-500 hover:text-[#108500]">
                            Forgot Password?
                        </a>
                    @endif
                </div>

                <button type="submit"
                    class="w-full bg-[#108500] hover:bg-[#0d6b00] text-white font-black py-3 md:py-4 rounded-lg shadow-lg shadow-green-200 transition-all uppercase tracking-widest text-xs md:text-sm">
                    <span wire:loading.remove>Log in to Vote</span>
                    <span wire:loading>Authenticating...</span>
                </button>
            </form>

            @if (Route::has('verify-account'))
                <p
                    class="text-center mt-6 md:mt-8 text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-widest">
                    Don't have an account?
                    <a href="{{ route('verify-account') }}" wire:navigate
                        class="text-[#108500] hover:text-[#0d6b00] underline">Verify Account</a>
                </p>
            @endif
        </div>
    </div>
</div>

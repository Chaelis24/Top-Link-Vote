<?php

use App\Events\UserLoggedInElsewhere;
use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\{Session, RateLimiter};
use App\Models\Setting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component {
    public LoginForm $form;
    public bool $isMaintenance = false;

    public function mount()
    {
        $settings = Setting::pluck('value', 'key')->toArray();
        $this->isMaintenance = isset($settings['maintenanceMode']) && (bool) $settings['maintenanceMode'];
    }

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

            $user = auth()->user();
            $currentSessionId = session()->getId();
            $otherSessions = DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $currentSessionId)->exists();

            if ($otherSessions) {
                broadcast(new UserLoggedInElsewhere($user->id))->toOthers();
                auth()->logoutOtherDevices($this->form->password);
                DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $currentSessionId)->delete();
            }

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
};

?>

<div class="fixed inset-0 z-[9999] overflow-y-auto bg-white flex items-center justify-center p-4 m-0 w-full h-full">
    <div class="absolute inset-0 bg-white"></div>

    <div
        class="relative z-10 max-w-4xl w-full bg-white shadow-2xl rounded-2xl overflow-hidden flex flex-col md:flex-row border border-gray-100 mx-2 md:mx-0">

        <!-- Left Section: Branding -->
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
                </p>
            </div>
        </div>

        @if ($isMaintenance)
            <div class="md:w-1/2 p-6 md:p-10 flex flex-col justify-center items-center bg-white text-center">
                <div class="mb-6">
                    <svg class="w-20 h-20 text-gray-500 mx-auto mb-4" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h2 class="text-2xl md:text-3xl font-bold text-[#252525] mb-2 tracking-tighter uppercase">System
                        Locked</h2>
                    <p class="text-gray-500 text-sm">We are currently performing system maintenance. Please come back
                        later.</p>
                </div>

                <button disabled
                    class="w-full bg-gray-400 text-white font-black py-3 md:py-4 rounded-lg shadow-lg uppercase tracking-widest text-xs md:text-sm cursor-not-allowed">
                    Under Maintenance
                </button>
            </div>
        @else
            <div class="md:w-1/2 p-6 md:p-10 flex flex-col justify-center bg-white">
                <div class="mb-6 md:mb-8">
                    <h2 class="text-2xl md:text-3xl font-bold text-[#252525] mb-1 md:mb-2 tracking-tighter">Sign in</h2>
                    <p class="text-gray-500 text-xs md:text-sm">Please log in with your Student Credentials.</p>
                </div>

                <form wire:submit="login" class="space-y-4 md:space-y-5">
                    <div>
                        <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">Student
                            ID</label>
                        <input wire:model="form.student_id" type="text" placeholder="e.g. 23-0001" required
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-sm">
                        <x-input-error :messages="$errors->get('form.student_id')" class="mt-2 text-red-600" />
                    </div>

                    <div x-data="{ show: false }" class="relative">
                        <label
                            class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">Password</label>
                        <input :type="show ? 'text' : 'password'" wire:model="form.password" placeholder="password"
                            required
                            class="w-full px-4 py-2.5 md:py-3 rounded-lg border border-gray-200 focus:ring-2 focus:ring-[#9cff00] focus:border-[#108500] outline-none transition-all text-sm">

                        <button type="button" @click="show = !show"
                            class="absolute right-3 top-[32px] md:top-[38px] text-[9px] font-black text-[#108500] uppercase">
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
                        class="w-full bg-[#108500] hover:bg-[#0d6b00] text-white font-black py-3 md:py-4 rounded-lg shadow-lg transition-all uppercase tracking-widest text-xs md:text-sm">
                        <span wire:loading.remove>Log in to Vote</span>
                        <span wire:loading>Authenticating...</span>
                    </button>
                </form>

                @if (Route::has('verify-account'))
                    <p
                        class="text-center mt-6 md:mt-8 text-[10px] md:text-xs font-bold text-gray-500 uppercase tracking-widest">
                        Don't have an account?
                        <a href="{{ route('verify-account') }}" wire:navigate
                            class="text-[#108500] hover:text-[#0d6b00] underline">
                            Verify Account
                        </a>
                    </p>
                @endif
            </div>
        @endif
    </div>
</div>

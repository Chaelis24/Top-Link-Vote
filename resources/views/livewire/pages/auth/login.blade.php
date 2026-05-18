<?php

use App\Models\Setting;
use Illuminate\Support\Str;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\DB;
use App\Events\UserLoggedInElsewhere;
use Illuminate\Support\Facades\{Session, RateLimiter};

new #[Layout('layouts.guest')] class extends Component {
    public LoginForm $form;
    public bool $isMaintenance = false;

    public function mount()
    {
        if (session()->has('swal')) {
            $this->dispatch('swal', session('swal'));
        }

        $settings = Setting::pluck('value', 'key')->toArray();
        $this->isMaintenance = isset($settings['maintenanceMode']) && (bool) $settings['maintenanceMode'];
    }

    public function login(): void
    {
        $throttleKey = Str::transliterate(Str::lower($this->form->student_id) . '|' . request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('form.student_id', "Too many login attempts. Retry in $seconds seconds.");

            session()->flash('swal', [
                'icon' => 'error',
                'title' => 'Account Locked Temporarily',
                'text' => "Please wait for $seconds seconds before trying again.",
            ]);
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
            throw $e;
        }
    }
};
?>
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

                <div class="md:w-1/2 p-6 md:p-10 flex flex-col justify-center bg-white">
                    <div class="mb-6 md:mb-8">
                        <h2 class="text-2xl md:text-3xl font-bold text-[#252525] mb-1 md:mb-2 tracking-tighter">Sign in
                        </h2>
                        <p class="text-gray-500 text-xs md:text-sm">Please log in with your Student Credentials.</p>
                    </div>

                    @if (session()->has('swal'))
                        <script>
                            Swal.fire({
                                icon: "{{ session('swal')['icon'] }}",
                                title: "{{ session('swal')['title'] }}",
                                text: "{{ session('swal')['text'] }}",
                                confirmButtonColor: '#108500',
                            });
                        </script>
                    @endif

                    <form wire:submit="login" class="space-y-4 md:space-y-5">
                        <div>
                            <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">
                                Student ID
                            </label>
                            <input wire:model="form.student_id" type="text" placeholder="e.g. 23-0001" required
                                class="w-full px-4 py-2.5 md:py-3 rounded-lg border transition-all text-sm outline-none focus:outline-none focus:ring-2
                                {{ $errors->has('form.student_id') ? 'border-red-500 focus:ring-red-200 focus:border-red-500' : 'border-gray-200 focus:ring-[#9cff00]/30 focus:border-[#108500]' }}">
                            @error('form.student_id')
                                <p class="text-[10px] text-red-500 font-bold mt-1 ms-1 uppercase italic">{{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div x-data="{ show: false }" class="relative">
                            <label class="text-[10px] md:text-xs font-bold uppercase text-gray-500 mb-1 block ms-1">
                                Password
                            </label>
                            <div class="relative">
                                <input :type="show ? 'text' : 'password'" wire:model="form.password"
                                    placeholder="password" required
                                    class="w-full px-4 py-2.5 md:py-3 rounded-lg border transition-all text-sm outline-none focus:outline-none focus:ring-2
                                {{ $errors->has('form.password') ? 'border-red-500 focus:ring-red-200 focus:border-red-500' : 'border-gray-200 focus:ring-[#9cff00]/30 focus:border-[#108500]' }}">
                                <button type="button" @click="show = !show"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-[9px] font-black {{ $errors->has('form.password') ? 'text-red-500' : 'text-[#108500]' }} uppercase">
                                    <span x-text="show ? 'Hide' : 'Show'"></span>
                                </button>
                            </div>
                            @error('form.password')
                                <p class="text-[10px] text-red-500 font-bold mt-1 ms-1 uppercase italic">{{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div
                            class="flex items-center justify-between text-[10px] md:text-xs font-bold uppercase tracking-tighter">
                            <label class="flex items-center text-[#252525] cursor-pointer">
                                <input type="checkbox" wire:model="form.remember"
                                    class="rounded border-gray-300 text-[#108500] shadow-sm focus:ring-[#9cff00]">
                                <span class="text-gray-500 hover:text-[#108500] ms-2">Remember me</span>
                            </label>
                            @if (Route::has('forgot-password'))
                                <a href="{{ route('forgot-password') }}" wire:navigate
                                    class="text-gray-500 hover:text-[#108500]">Forgot Password?</a>
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
                                class="text-[#108500] hover:text-[#0d6b00] underline">Verify Account</a>
                        </p>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

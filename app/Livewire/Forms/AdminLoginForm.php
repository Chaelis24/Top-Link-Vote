<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\{Auth, Hash, RateLimiter};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * Handles admin authentication form validation and login logic.
 *
 * Extends Livewire's Form object to encapsulate admin-specific
 * login fields, rate limiting, and credential verification.
 */
class AdminLoginForm extends Form
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Authenticate the admin using validated credentials.
     *
     * Logs the admin in and clears the rate limiter on success.
     */
    public function authenticate(): void
    {
        Auth::login($this->validateCredentials(), $this->remember);
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Validate the provided credentials against the database.
     *
     * @return \App\Models\User
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateCredentials(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::where('email', $this->email)->first();

        if (!$user || !Hash::check($this->password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        return $user;
    }

    /**
     * Ensure the authentication request is not rate limited.
     *
     * Throws a ValidationException with a throttle message
     * if the maximum number of attempts has been exceeded.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the current request.
     *
     * Combines the admin email with the request IP address.
     *
     * @return string
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }
}

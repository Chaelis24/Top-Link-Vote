<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

/**
 * Handles student authentication form validation and login logic.
 *
 * Extends Livewire's Form object to encapsulate student-specific
 * login fields, credential lookups, and rate limiting.
 */
class LoginForm extends Form
{
    #[Validate('required|string')]
    public string $student_id = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Authenticate the student using validated credentials.
     *
     * Logs the student in and clears the rate limiter on success.
     */
    public function authenticate(): void
    {
        Auth::login($this->validateCredentials(), $this->remember);
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Validate the provided credentials against the database.
     *
     * Looks up the user by associated student ID or email address.
     *
     * @return \App\Models\User
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateCredentials(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::whereHas('student', function ($query) {
            $query->where('student_id', $this->student_id);
        })->orWhere('email', $this->student_id)->first();

        if (!$user || !Hash::check($this->password, $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.student_id' => 'Invalid credentials.',
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
            'form.student_id' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the current request.
     *
     * Combines the student identifier with the request IP address.
     *
     * @return string
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->student_id) . '|' . request()->ip());
    }
}

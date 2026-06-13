<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Validates incoming requests for updating admin profile and password
 * settings. Both `profileRules` and `passwordRules` are exposed as
 * static methods so the Livewire component can use them without
 * re-instantiating the form request.
 */
class AdminSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasRole('admin');
    }

    /**
     * Validation rules for updating the admin's name and email.
     *
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\Password>>
     */
    public static function profileRules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . Auth::id()],
        ];
    }

    /**
     * Validation rules for changing the admin's password.
     *
     * @return array<string, array<int, string|\Illuminate\Validation\Rules\Password>>
     */
    public static function passwordRules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ];
    }
}

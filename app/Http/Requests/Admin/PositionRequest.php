<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Validates the creation and editing of election positions.
 * Enforces required fields for name, max winners, and priority,
 * with an optional student-department association.
 */
class PositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'max_winners' => 'required|integer|min:1|max:50',
            'priority' => 'required|integer|min:1',
            'student_department' => 'nullable|string|max:255',
        ];
    }
}

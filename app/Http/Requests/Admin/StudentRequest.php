<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Validates admin actions on student records. Provides separate
 * rule sets for editing a student's profile details and for
 * importing students via CSV upload.
 */
class StudentRequest extends FormRequest
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
    /**
     * Validation rules for editing an existing student's profile via the
     * admin panel. The `editForm.*` prefix matches Livewire nested data.
     *
     * @return array<string, string>
     */
    public static function updateRules(): array
    {
        return [
            'editForm.first_name'  => 'required|string|max:255',
            'editForm.middle_name' => 'nullable|string|max:255',
            'editForm.last_name'   => 'required|string|max:255',
            'editForm.suffix'      => 'nullable|string|max:10',
            'editForm.block_id'    => 'required|exists:blocks,id',
            'editForm.status'      => 'required|in:active,inactive,suspended',
            'editForm.phone'       => 'nullable|numeric',
            'editForm.birthday'    => 'nullable|date',
            'editForm.gender'      => 'nullable|in:Male,Female,Other',
        ];
    }

    /**
     * Validation rules for CSV imports. Accepts files up to 5 MB
     * with `.csv` or `.txt` extensions.
     *
     * @return array<string, string>
     */
    public static function importRules(): array
    {
        return [
            'csvFile' => 'required|file|max:5120|mimes:csv,txt',
        ];
    }
}

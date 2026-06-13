<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Validates candidate profile updates submitted from the student
 * portal. Includes party name, platform details, academic grade,
 * and previous leadership experience.
 */
class UpdateCandidateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasRole('candidate');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'party_name' => 'nullable|string|max:255',
            'tagline' => 'required|string|max:255',
            'platform_title' => 'required|string|max:255',
            'agenda' => 'required|string',
            'average_grade' => 'nullable|numeric|between:1.0,5.0',
            'previous_position' => 'nullable|array',
            'previous_position.*' => 'nullable|string|max:255',
            'previous_school_project' => 'nullable|array',
            'previous_school_project.*' => 'nullable|string|max:255',
        ];
    }
}

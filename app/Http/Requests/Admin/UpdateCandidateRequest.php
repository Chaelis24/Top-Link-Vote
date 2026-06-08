<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateCandidateRequest extends FormRequest
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
            'editForm.position_id' => 'required',
            'editForm.party_name' => 'required|string|max:255',
            'editForm.platform_title' => 'required|string|max:255',
            'editForm.previous_position.*' => 'nullable|string|max:100',
            'editForm.previous_school_project.*' => 'nullable|string|max:100',
            'editForm.average_grade' => 'nullable|string|max:10',
            'editForm.achievements' => 'nullable|string',
            'editForm.tagline' => 'nullable|string|max:255',
            'editForm.agenda' => 'nullable|string',
            'candidate_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048|dimensions:min_width=100,min_height=100',
        ];
    }

    public function attributes(): array
    {
        return [
            'editForm.party_name' => 'party name',
            'editForm.position_id' => 'position',
            'editForm.platform_title' => 'platform title',
            'editForm.previous_position.*' => 'previous position',
            'editForm.previous_school_project.*' => 'previous project',
            'editForm.achievements' => 'achievements',
            'editForm.average_grade' => 'average grade',
            'editForm.tagline' => 'tagline',
            'editForm.agenda' => 'agenda',
            'candidate_photo' => 'candidate photo',
        ];
    }
}

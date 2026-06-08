<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ElectionCycleRequest extends FormRequest
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
            'cycle_name' => 'required|min:5',
            'academic_year' => 'required|string|size:9|regex:/^\d{4}-\d{4}$/',
            'filing_start' => 'required|date',
            'filing_end' => 'required|date|after_or_equal:filing_start',
            'campaign_start' => 'required|date|after:filing_end',
            'campaign_end' => 'required|date|after:campaign_start',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'results_date' => 'required|date|after:end_date',
        ];
    }
}

<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class CompanyUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name_en' => ['required', 'string'],
            'name_fa' => ['nullable', 'string'],
            'name_pa' => ['nullable', 'string'],
            'abbreviation' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'logo' => ['nullable', 'string'],
            'calendar_type' => ['nullable', 'string'],
            'working_style' => ['nullable', 'string'],
            'business_type' => ['nullable', 'string'],
            'locale' => ['nullable', 'string'],
            'currency_id' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'website' => ['nullable', 'string'],
            'invoice_description' => ['nullable', 'string'],
        ];
    }
}

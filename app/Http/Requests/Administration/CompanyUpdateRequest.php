<?php

namespace App\Http\Requests\Administration;

use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
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
            'abbreviation' => ['required', 'string'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'logo' => 'nullable|image|max:2048',
            'calendar_type' => ['required', 'in:' . implode(',', array_column(CalendarType::cases(), 'value'))],
            'working_style' => ['required', 'in:' . implode(',', array_column(WorkingStyle::cases(), 'value'))],
            'business_type' => ['required', 'in:' . implode(',', array_column(BusinessType::cases(), 'value'))],
            'locale' => ['required', 'in:' . implode(',', array_column(Locale::cases(), 'value'))],
            'currency_id' => ['required', 'exists:currencies,id'],
            'email' => ['nullable', 'email'],
            'website' => ['nullable', 'string'],
            'invoice_description' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the uploaded file.
     */
    // public function getFile($key)
    public function getFile($key)
    {
        return $this->allFiles()[$key] ?? null;
    }

    // /**
    //  * Check if file exists.
    //  */
    public function hasFile($key)
    {
        return isset($this->allFiles()[$key]);
    }
}

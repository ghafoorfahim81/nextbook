<?php

namespace App\Http\Requests\Administration;

use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use Illuminate\Foundation\Http\FormRequest;

class CompanyStoreRequest extends FormRequest
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
            'name_en' => ['required', 'string', 'max:255'],
            'name_fa' => ['nullable', 'string', 'max:255'],
            'name_pa' => ['nullable', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'calendar_type' => ['required', 'in:' . implode(',', array_column(CalendarType::cases(), 'value'))],
            'working_style' => ['required', 'in:' . implode(',', array_column(WorkingStyle::cases(), 'value'))],
            'business_type' => ['required', 'in:' . implode(',', array_column(BusinessType::cases(), 'value'))],
            'locale' => ['required', 'in:' . implode(',', array_column(Locale::cases(), 'value'))],
            'currency_id' => ['required', 'exists:currencies,id'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'invoice_description' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the uploaded file.
     */
    public function getFile($key)
    {
        return $this->allFiles()[$key] ?? null;
    }

    /**
     * Check if file exists.
     */
    public function hasFile($key)
    {
        return isset($this->allFiles()[$key]);
    }
}

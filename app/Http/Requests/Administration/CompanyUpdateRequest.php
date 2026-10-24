<?php

namespace App\Http\Requests\Administration;

use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Enums\CostingMethod;
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
            'costing_method' => ['required', 'in:' . implode(',', array_column(CostingMethod::cases(), 'value'))],
            'email' => ['nullable', 'email'],
            'website' => ['nullable', 'string'],
            'invoice_description' => ['nullable', 'string'],
            // The licence belongs to the company, and its branches share it.
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_issued_at' => ['nullable', 'date'],
            'license_expires_at' => ['nullable', 'date', 'after_or_equal:license_issued_at'],

            // The edit page's settings step: the company's own settings, posted
            // as one JSON field because the rest of the form is FormData.
            'settings' => ['sometimes', 'array'],
            ...\App\Http\Requests\Preferences\UpdatePreferencesRequest::companyRules('settings.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('settings'))) {
            $this->merge(['settings' => json_decode($this->input('settings'), true) ?: []]);
        }
    }

    /**
     * Readable field names for the messages, in the user's language.
     */
    public function attributes(): array
    {
        $names = __('general.company_fields');

        return is_array($names) ? $names : [];
    }

    /**
     * The company row's attributes: the validated fields without the settings,
     * with licence dates stored as Gregorian (the date picker follows the
     * company's calendar).
     *
     * @return array<string, mixed>
     */
    public function companyAttributes(): array
    {
        $attributes = collect($this->validated())->except(['logo', 'settings'])->all();

        foreach (['license_issued_at', 'license_expires_at'] as $field) {
            if (array_key_exists($field, $attributes)) {
                $attributes[$field] = filled($attributes[$field])
                    ? app(\App\Services\DateConversionService::class)->toGregorian((string) $attributes[$field])
                    : null;
            }
        }

        return $attributes;
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

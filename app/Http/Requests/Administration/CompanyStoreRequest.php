<?php

namespace App\Http\Requests\Administration;

use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Enums\CostingMethod;
use App\Services\CompanySetupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            // The image rules only for an uploaded file: the sign-up form keeps
            // a base64 preview in the same field, which is not saved.
            'logo' => $this->hasFile('logo') ? ['image', 'mimes:jpeg,png,jpg,gif', 'max:2048'] : ['nullable'],
            'calendar_type' => ['required', 'in:' . implode(',', array_column(CalendarType::cases(), 'value'))],
            'working_style' => ['required', 'in:' . implode(',', array_column(WorkingStyle::cases(), 'value'))],
            'business_type' => ['required', 'in:' . implode(',', array_column(BusinessType::cases(), 'value'))],
            'locale' => ['required', 'in:' . implode(',', array_column(Locale::cases(), 'value'))],
            // A currency code from the catalog, not a currency id: a company
            // being created has no currencies yet, and an id would name one of
            // another company's. CompanySetupService creates and marks it.
            'base_currency' => ['required', 'string', Rule::in(CompanySetupService::baseCurrencyCodes())],
            // Optional: the sign-up form does not ask. FIFO when left out.
            'costing_method' => ['nullable', 'in:' . implode(',', array_column(CostingMethod::cases(), 'value'))],
            // The licence belongs to the company, and its branches share it.
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_issued_at' => ['nullable', 'date'],
            'license_expires_at' => ['nullable', 'date', 'after_or_equal:license_issued_at'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'invoice_description' => ['nullable', 'string'],

            // The wizard's settings step (phase 6): the company's own settings,
            // posted as one JSON field because the rest of the form is FormData.
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
     * A wizard step asks for its own fields only (Precognition-Validate-Only).
     * The settings step names just "settings", which stands for every rule
     * under it; Laravel would otherwise keep only an exact match.
     */
    public function filterPrecognitiveRules($rules)
    {
        if (! $this->headers->has('Precognition-Validate-Only')) {
            return $rules;
        }

        $only = explode(',', (string) $this->header('Precognition-Validate-Only'));

        return collect($rules)
            ->filter(fn ($rule, $key) => collect($only)->contains(fn ($field) => $key === $field || str_starts_with($key, $field.'.')))
            ->all();
    }

    /**
     * Readable field names for the messages ("The English name field is
     * required.", not "The name en field…"), in the user's language.
     */
    public function attributes(): array
    {
        $names = __('general.company_fields');

        return is_array($names) ? $names : [];
    }

    /**
     * The company row's attributes: everything validated except the base
     * currency (CompanySetupService takes that separately), with an uploaded
     * logo stored and the costing method defaulted. Sign-up and the companies
     * screen both build the company from this.
     *
     * @return array<string, mixed>
     */
    public function companyAttributes(): array
    {
        $attributes = collect($this->validated())->except(['base_currency', 'logo', 'settings'])->all();

        // The company's settings, if the wizard sent any; the rest come from
        // the defaults through App\Support\Settings.
        $settings = \App\Support\Settings::companyPart((array) $this->validated('settings', []));
        if ($settings !== []) {
            $attributes['preferences'] = $settings;
        }

        if ($this->hasFile('logo')) {
            $attributes['logo'] = $this->file('logo')->store('company-logos', 'public');
        }

        $attributes['costing_method'] ??= CostingMethod::FIFO->value;

        // The date picker follows the chosen calendar; stored dates are
        // Gregorian, as everywhere else.
        foreach (['license_issued_at', 'license_expires_at'] as $field) {
            if (! empty($attributes[$field])) {
                $attributes[$field] = app(\App\Services\DateConversionService::class)->toGregorian((string) $attributes[$field]);
            }
        }

        return $attributes;
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

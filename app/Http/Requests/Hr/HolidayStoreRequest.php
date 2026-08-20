<?php

namespace App\Http\Requests\Hr;

use App\Enums\HolidayType;
use App\Services\DateConversionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HolidayStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'holiday_type' => ['required', Rule::in(HolidayType::values())],
            'is_recurring' => ['nullable', 'boolean'],
            'is_paid' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $end = $this->input('end_date');

            if (! $end || ! $this->input('date')) {
                return;
            }

            $dates = app(DateConversionService::class);

            if ($dates->toGregorian((string) $end) < $dates->toGregorian((string) $this->input('date'))) {
                $validator->errors()->add('end_date', __('hr.validation.end_date_after_start'));
            }
        });
    }
}

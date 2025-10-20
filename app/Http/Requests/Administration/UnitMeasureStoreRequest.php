<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class UnitMeasureStoreRequest extends FormRequest
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
            'metric' => ['required', 'array'],
            'measure' => ['required', 'array'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Custom validation to check for duplicate measures within the same quantity
            $metricData = $this['metric'];
            $measureData = $this['measure'];

            if ($metricData && $measureData) {
                $metric = \App\Models\Administration\Quantity::where('unit', $metricData['unit'])->first();
                if ($metric) {
                    $existingMeasure = $metric->measures()->where('name', $measureData['name'])->whereNull('deleted_at')->first();
                    if ($existingMeasure) {
                        $validator->errors()->add('measure', 'A measure with this name already exists for the selected quantity type.');
                    }
                }
            }
        });
    }
}

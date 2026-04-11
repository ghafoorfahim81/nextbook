<?php

namespace App\Http\Requests\Administration;

use App\Models\Administration\Quantity;
use App\Models\Administration\UnitMeasure;
use Illuminate\Foundation\Http\FormRequest;

class UnitMeasureUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'numeric'],
            'symbol' => ['required', 'string', 'max:50'],
            'quantity_id' => ['required', 'string', 'exists:quantities,id'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $currentUnitMeasureId = $this->route('unitMeasure')?->id;
            $quantityId = $this->input('quantity_id');
            $name = $this->input('name');

            if (!$currentUnitMeasureId || !$quantityId || !$name) {
                return;
            }

            $metric = Quantity::find($quantityId);
            if (! $metric) {
                return;
            }

            $existingMeasure = UnitMeasure::query()
                ->where('quantity_id', $metric->id)
                ->where('name', $name)
                ->where('id', '!=', $currentUnitMeasureId)
                ->whereNull('deleted_at')
                ->first();

            if ($existingMeasure) {
                $validator->errors()->add('name', 'A measure with this name already exists for the selected quantity type.');
            }
        });
    }
}

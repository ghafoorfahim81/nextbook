<?php

namespace App\Http\Requests\Sale;

use App\Enums\SaleReturnReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleReturnStoreRequest extends FormRequest
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
            'number' => ['required', 'integer', 'min:1', 'unique:sale_returns,number,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'sale_id' => ['required', 'string', 'exists:sales,id'],
            'date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', Rule::in(SaleReturnReason::values())],
            'description' => ['nullable', 'string'],
            'item_list' => ['required', 'array', 'min:1'],
            'item_list.*.sale_item_id' => ['required', 'string', 'exists:sale_items,id'],
            'item_list.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $ids = collect($this->input('item_list', []))->pluck('sale_item_id');

            if ($ids->duplicates()->isNotEmpty()) {
                $validator->errors()->add('item_list', __('Each sale item can only appear once per return.'));
            }
        });
    }
}

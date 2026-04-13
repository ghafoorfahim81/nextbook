<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;

class SaleReceiveStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_id' => ['required', 'string'],
            'receipt_id' => ['required', 'string'],
            'amount' => ['required', 'numeric'],
            'created_by' => ['required'],
            'updated_by' => ['nullable'],
            'deleted_by' => ['nullable'],
        ];
    }
}

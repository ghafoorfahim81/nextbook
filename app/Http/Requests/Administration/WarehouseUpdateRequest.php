<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:256',
                Rule::unique('warehouses')
                    ->ignore($this->route('warehouse'))
                    ->whereNull('deleted_at'),
            ],
            'address' => ['nullable', 'string'],
            'is_main' => ['nullable', 'boolean'],
        ];
    }
}


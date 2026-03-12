<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class WarehouseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'unique:warehouses,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'address' => ['nullable', 'string'],
            'is_main' => ['nullable', 'boolean'],
        ];
    }
}


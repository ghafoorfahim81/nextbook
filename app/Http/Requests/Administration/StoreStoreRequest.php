<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:stores,name,NULL,id,branch_id,NULL,deleted_at,NULL'],
            'address' => ['nullable', 'string'],
            'is_main' => ['nullable', 'boolean'],
        ];
    }
}

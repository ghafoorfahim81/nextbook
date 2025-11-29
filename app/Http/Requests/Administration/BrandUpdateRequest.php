<?php
namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', Rule::unique('brands')->ignore($this->route('brand'))->whereNull('deleted_at')],
            'legal_name' => ['nullable', 'string'],
            'registration_number' => ['nullable', 'string'],
            'logo' => ['nullable', 'string'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'website' => ['nullable', 'url'],
            'industry' => ['nullable', 'string'],
            'type' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'country' => ['nullable', 'string'],
        ];
    }
}

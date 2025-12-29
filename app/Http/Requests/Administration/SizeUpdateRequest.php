<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SizeUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', Rule::unique('sizes')->ignore($this->route('size'))->whereNull('deleted_at')->where('branch_id', $this->user()->current_branch_id)],
            'code' => ['required', 'string', Rule::unique('sizes')->ignore($this->route('size'))->whereNull('deleted_at')],
        ];
    }
}

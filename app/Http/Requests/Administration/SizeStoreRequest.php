<?php

namespace App\Http\Requests\Administration;

use Illuminate\Foundation\Http\FormRequest;

class SizeStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:sizes,name,NULL,id,branch_id,' . $this->user()->current_branch_id . ',deleted_at,NULL'],
            'code' => ['required', 'string', 'unique:sizes,code,NULL,id,branch_id,' . $this->user()->current_branch_id . ',deleted_at,NULL'],
        ];
    }
}

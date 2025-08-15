<?php
// app/Http/Requests/Inventory/FastEntryRequest.php
namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class FastOpeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // or Gate/Policy
    }

    public function rules(): array
    {
        return [
            'items'                   => ['required','array'],
            'items.*.quantity'        => ['required','numeric'],
            'items.*.expire_date'     => ['nullable','date'],
            'items.*.purchase_price'   => ['required','numeric'],
            'items.*.unit_measure_id' => ['required','exists:unit_measures,id'],
            'items.*.store_id'        => ['required','exists:stores,id'],
        ];
    }

}

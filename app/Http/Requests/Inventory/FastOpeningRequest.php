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
            'items'                   => ['required','array','min:1'],
            'items.*.item_id'         => ['required','string'],
            'items.*.quantity'        => ['required','numeric'],
            'items.*.batch'           => ['nullable','string'],
            'items.*.expire_date'     => ['nullable','date'],
            'items.*.cost'            => ['required','numeric'],
            'items.*.unit_measure_id' => ['required','exists:unit_measures,id'],
        ];
    }

}

<?php
// app/Http/Requests/Inventory/FastEntryRequest.php
namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class FastEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // or Gate/Policy
    }
 
    public function rules(): array
    {
        return [
            'items'                       => ['required','array','min:1'],
            'items.*.name'                => ['required','string','max:255','unique:items,name,NULL,id,deleted_at,NULL'],
            'items.*.code'                => ['nullable','string','max:50','unique:items,code,NULL,id,deleted_at,NULL'],
            'items.*.category_id'         => ['nullable','exists:categories,id'],
            'items.*.measure_id'          => ['required','exists:unit_measures,id'],
            'items.*.brand_id'            => ['nullable','exists:brands,id'],
            'items.*.purchase_price'      => ['nullable','numeric','min:0'],
            'items.*.mrp_rate'            => ['nullable','numeric','min:0'],
            'items.*.batch'               => ['nullable','string','max:100'],
            'items.*.expire_date'         => ['nullable','date'],
            'items.*.quantity'            => ['nullable','numeric','min:0'],
            'items.*.store_id'            => ['nullable','exists:stores,id'],
        ];
    }

    public function prepareForValidation(): void
    {
        // Trim & coerce simple types if you want
//        $items = collect($this->input('items', []))
//            ->map(function($r) {
//                $r['purchase_price'] = $r['purchase_price'] === '' ? null : $r['purchase_price'];
//                $r['mrp_rate']       = $r['mrp_rate'] === '' ? null : $r['mrp_rate'];
//                $r['quantity']       = $r['quantity'] === '' ? null : $r['quantity'];
//                return $r;
//            })
//            // drop completely empty rows (safety)
//            ->filter(function($r){
//                return collect($r)->except(['_key'])->some(fn($v) => $v !== null && $v !== '');
//            })
//            ->values();
//
//        $this->merge(['items' => $items]);
    }
}

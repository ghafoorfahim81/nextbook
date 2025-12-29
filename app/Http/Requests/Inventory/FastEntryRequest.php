<?php
// app/Http/Requests/Inventory/FastEntryRequest.php
namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class FastEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // or Gate/Policy
    }
 
    public function rules(): array
    {
        $branchId = $this->user()?->branch_id;

        return [
            'items'                       => ['required','array','min:1'],
            'items.*.name'                => ['required','string','max:255', Rule::unique('items')->where(fn ($q) => $q
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at')
                    )],
            'items.*.code'                => ['nullable','string','max:50', Rule::unique('items')->where(fn ($q) => $q
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at')
                    )],
            'items.*.category_id'         => ['nullable','exists:categories,id'],
            'items.*.measure_id'          => ['required','exists:unit_measures,id'],
            'items.*.brand_id'            => ['nullable','exists:brands,id'],
            'items.*.purchase_price'      => ['nullable','numeric','min:0'],
            'items.*.sale_price'          => ['nullable','numeric','min:0'],
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
//                $r['sale_price']     = $r['sale_price'] === '' ? null : $r['sale_price'];
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

<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'generic_name' => $this->generic_name,
            'packing' => $this->packing,
            'barcode' => $this->barcode,
            'unit_measure_id' => $this->unit_measure_id,
            'measure'  => $this->unitMeasure->name,
            'company_id' => $this->company_id,
            'company' => $this->company->name,
            'category_id' => $this->category_id,
            'category' => $this->category->name,
            'minimum_stock' => $this->minimum_stock,
            'maximum_stock' => $this->maximum_stock,
            'colors' => $this->colors,
            'size' => $this->size,
            'photo' => $this->photo,
            'purchase_price' => $this->purchase_price,
            'cost' => $this->cost,
            'mrp_rate' => $this->mrp_rate,
            'rate_a' => $this->rate_a,
            'rate_b' => $this->rate_b,
            'rate_c' => $this->rate_c,
            'rack_no' => $this->rack_no,
            'fast_search' => $this->fast_search,
            'description' => $this->description,
            'branch_id' => $this->branch_id,
        ];
    }
}

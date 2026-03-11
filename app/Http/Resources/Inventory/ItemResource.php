<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Inventory\StockOpeningResource;
use App\Enums\ItemType;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use App\Enums\StockMovementType;
use App\Http\Resources\Inventory\StockMovementResource;
use App\Http\Resources\Administration\BrandResource;
use App\Http\Resources\Administration\SizeResource;
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
            'measure'  => $this->unitMeasure?->name,
            'unitMeasure'  => $this->unitMeasure,
            'brand_id' => $this->brand_id,
            'brand' => BrandResource::make($this->whenLoaded('brand')),
            'brand_name' => $this->brand?->name,
            'category_id' => $this->category_id,
            'category' => $this->category?->name,
            'asset_account_id' => $this->asset_account_id,
            'income_account_id' => $this->income_account_id,
            'cost_account_id' => $this->cost_account_id,
            'asset_account' => AccountResource::make($this->whenLoaded('assetAccount')),
            'income_account' => AccountResource::make($this->whenLoaded('incomeAccount')),
            'cost_account' => AccountResource::make($this->whenLoaded('costAccount')),
            'minimum_stock' => $this->minimum_stock,
            'maximum_stock' => $this->maximum_stock,
            'colors' => $this->colors,
            'size' => SizeResource::make($this->whenLoaded('size')),
            'size_id' => $this->size_id,
            'purchase_price' => $this->purchase_price,
            'cost' => $this->cost,
            'sale_price' => $this->sale_price,
            'margin_percentage' => $this->margin_percentage,
            'rate_a' => $this->rate_a,
            'rate_b' => $this->rate_b,
            'rate_c' => $this->rate_c,
            'rack_no' => $this->rack_no,
            'fast_search' => $this->fast_search,
            'is_batch_tracked' => $this->is_batch_tracked,
            'is_expiry_tracked' => $this->is_expiry_tracked,
            'sku' => $this->sku,
            'item_type' => $this->item_type ? $this->item_type?->getLabel() : null,
            'item_type_id' => $this->item_type,
            'stock_count' => $this->stocks->where('movement_type', StockMovementType::IN->value)->sum('quantity'),
            'stock_out_count' => $this->stocks->where('movement_type', StockMovementType::OUT->value)->sum('quantity'),
            'branch_id' => $this->branch_id,
            'on_hand' => number_format($this->onHand(), 2),
            'avg_cost' => number_format($this->avgCost(), 2),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
            'openings' => StockMovementResource::collection($this->whenLoaded('openings')),
        ];
    }


}

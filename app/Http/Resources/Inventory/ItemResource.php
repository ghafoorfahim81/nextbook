<?php

namespace App\Http\Resources\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Inventory\StockOpeningResource;
use App\Enums\ItemType;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
use App\Http\Resources\Inventory\StockMovementResource;
use Illuminate\Support\Facades\DB;
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
            // Calculate total in quantity in item's base unit
            // Only provide stock_count and stock_out_count if 'stocks' relation is loaded
            'stock_count' => $this->whenLoaded('stocks', function () {
                $sum = $this->stocks
                    ->where('movement_type', StockMovementType::IN->value)
                    ->sum(function ($stock) {
                        // If unit_measure_id differs (e.g. sale in box, item in each), normalize to item unit
                        if ((string)$stock->unit_measure_id === (string)$this->unit_measure_id || !$stock->unit_measure_id) {
                            return $stock->quantity;
                        }
                        $stockUnit = (float) optional($stock->unitMeasure)->unit ?: 1;
                        $itemUnit = (float) optional($this->unitMeasure)->unit ?: 1;
                        if ($itemUnit == 0) $itemUnit = 1; // avoid div 0
                        return $stock->quantity * ($stockUnit / $itemUnit);
                    });
                return number_format($sum, 2);
            }),
            'stock_out_count' => $this->whenLoaded('stocks', function () {
                $sum = $this->stocks
                    ->where('movement_type', StockMovementType::OUT->value)
                    ->sum(function ($stock) {
                        if ((string)$stock->unit_measure_id === (string)$this->unit_measure_id || !$stock->unit_measure_id) {
                            return $stock->quantity;
                        }
                        $stockUnit = (float) optional($stock->unitMeasure)->unit ?: 1;
                        $itemUnit = (float) optional($this->unitMeasure)->unit ?: 1;
                        if ($itemUnit == 0) $itemUnit = 1;
                        return $stock->quantity * ($stockUnit / $itemUnit);
                    });
                return number_format($sum, 2);
            }),
            'branch_id' => $this->branch_id,
            'on_hand' => number_format($this->onHand(), 2),
            'avg_cost' => $this->avg_cost,
            // 'avg_cost' => number_format($this->avg_cost, 2),
            'stock_value' => (function () {
                $inValue   = StockMovementType::IN->value;
                $voided    = StockStatus::VOIDED->value;
                $cancelled = StockStatus::CANCELLED->value;
                $value = DB::table('stock_movements as sm')
                    ->where('sm.item_id', $this->id)
                    ->where('sm.branch_id', $this->branch_id)
                    ->whereNull('sm.deleted_at')
                    ->whereNotIn('sm.status', [$voided, $cancelled])
                    ->selectRaw(
                        "COALESCE(SUM(CASE WHEN sm.movement_type = ? THEN sm.quantity * sm.unit_cost ELSE -(sm.quantity * sm.unit_cost) END), 0) as total_value",
                        [$inValue]
                    )
                    ->value('total_value');
                return number_format(max(0, (float) $value), 2);
            })(),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
            'openings' => StockMovementResource::collection($this->whenLoaded('openings')),
        ];
    }


}

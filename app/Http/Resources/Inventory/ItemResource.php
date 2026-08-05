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
use App\Http\Resources\AttachmentResource;
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
            'is_color_tracked' => $this->is_color_tracked,
            'is_size_tracked' => $this->is_size_tracked,
            'sku' => $this->sku,
            'item_type' => $this->item_type ? $this->item_type?->getLabel() : null,
            'item_type_id' => $this->item_type,

            // --- Behaviour flags -------------------------------------------
            'is_active' => (bool) $this->is_active,
            'is_stockable' => (bool) $this->is_stockable,
            'is_sellable' => (bool) $this->is_sellable,
            'is_purchasable' => (bool) $this->is_purchasable,
            'is_serial_tracked' => (bool) $this->is_serial_tracked,
            'is_weighted' => (bool) $this->is_weighted,
            'has_variants' => (bool) $this->has_variants,
            'allow_negative_stock' => (bool) $this->allow_negative_stock,
            'requires_prescription' => (bool) $this->requires_prescription,
            'is_controlled' => (bool) $this->is_controlled,

            'costing_method' => $this->costing_method?->value,
            'pricing_method' => $this->pricing_method?->value,

            // --- Physical ---------------------------------------------------
            'description' => $this->description,
            'photo' => $this->photo,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,

            // --- Sourcing & compliance ---------------------------------------
            'manufacturer' => $this->manufacturer,
            'model' => $this->model,
            'country_of_origin' => $this->country_of_origin,
            'hs_code' => $this->hs_code,
            'warranty_months' => $this->warranty_months,
            'shelf_life_days' => $this->shelf_life_days,
            'min_shelf_life_percent' => $this->min_shelf_life_percent,
            'storage_zone' => $this->storage_zone,

            // --- Planning ----------------------------------------------------
            'reorder_quantity' => $this->reorder_quantity,
            'lead_time_days' => $this->lead_time_days,
            'default_warehouse_id' => $this->default_warehouse_id,

            'variants' => ItemVariantResource::collection($this->whenLoaded('variants')),
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
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }


}

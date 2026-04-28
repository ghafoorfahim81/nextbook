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
class ItemListResource extends JsonResource
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
            'measure'  => $this->unitMeasure?->name,
            'unitMeasure'  => $this->unitMeasure, 
            'category' => $this->category?->name, 
            'cost' => $this->cost, 
            'on_hand' => number_format($this->onHand(), 2),
            'avg_cost' => number_format($this->avgCost(), 2), 
        ];
    }


}

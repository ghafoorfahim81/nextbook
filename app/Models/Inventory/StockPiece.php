<?php

namespace App\Models\Inventory;

use App\Enums\ItemCondition;
use App\Enums\PieceStatus;
use App\Models\Administration\Warehouse;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One physical object you can pick up.
 *
 * stock_balances says how many; this says which ones. Used where an individual
 * object must be traced, warranted or priced on its own: an IMEI, a VIN, a
 * hallmark, a carpet with its own measured dimensions.
 */
class StockPiece extends Model
{
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasSearch,
        HasSorting, HasBranch, BranchSpecific, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'item_id',
        'variant_id',
        'lot_id',
        'warehouse_id',
        'identifiers',
        'attributes',
        'measured_quantity',
        'unit_cost',
        'sale_price',
        'condition',
        'status',
        'purchase_item_id',
        'warranty_end_date',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'item_id' => 'string',
            'variant_id' => 'string',
            'lot_id' => 'string',
            'warehouse_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'identifiers' => 'array',
            'attributes' => 'array',
            'measured_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'condition' => ItemCondition::class,
            'status' => PieceStatus::class,
            'warranty_end_date' => 'date',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['item.name', 'item.code', 'variant.sku'];
    }

    /**
     * Find a piece by any of its identifiers — IMEI, VIN, hallmark, serial.
     *
     * Uses the GIN index on `identifiers`, so a counter scan stays a single
     * indexed lookup regardless of which identifier the operator typed.
     */
    public function scopeWhereIdentifier(Builder $query, string $value): Builder
    {
        return $query->whereRaw(
            "identifiers @> ANY (ARRAY[
                jsonb_build_object('serial', ?::text),
                jsonb_build_object('imei1', ?::text),
                jsonb_build_object('imei2', ?::text),
                jsonb_build_object('vin', ?::text),
                jsonb_build_object('hallmark', ?::text)
             ])",
            array_fill(0, 5, $value)
        );
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where('status', PieceStatus::IN_STOCK->value);
    }

    /**
     * The amount this piece contributes to stock: 1 for a phone, the measured
     * area or weight for a carpet or a gold item.
     */
    public function sellableQuantity(): float
    {
        return $this->measured_quantity !== null
            ? (float) $this->measured_quantity
            : 1.0;
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'variant_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}

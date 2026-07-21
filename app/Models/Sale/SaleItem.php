<?php

namespace App\Models\Sale;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
class SaleItem extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, HasDependencyCheck, SoftDeletes, BranchSpecific, HasBranch;

    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'sale_id',
        'item_id',
        'batch',
        'color',
        'expire_date',
        'quantity',
        'unit_measure_id',
        'unit_price',
        'discount',
        'size_id',
        'net_unit_cost',
        'free',
        'tax',
        'warehouse_id',
        'created_by',
        'updated_by',
        'branch_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_id' => 'string',
            'item_id' => 'string',
            'batch' => 'string',
            'expire_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_measure_id' => 'string',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'free' => 'decimal:2',
            'tax' => 'decimal:2',
            'warehouse_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'branch_id' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'batch',
            'expire_date',
            'quantity',
            'unit_price',
            'discount',
            'free',
            'tax',
            'sale.number',
            'item.name',
            'item.code',
            'unitMeasure.name',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Item::class);
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\UnitMeasure::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class, 'warehouse_id');
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    /**
     * Sum of quantity already claimed by non-reversed sale returns for this line.
     * Includes DRAFT returns so two concurrent drafts cannot both claim the same units.
     */
    public function returnedQuantity(?string $excludingSaleReturnId = null): float
    {
        return (float) $this->returnItems()
            ->whereHas('saleReturn', function ($query) {
                $query->whereIn('status', [
                    TransactionStatus::DRAFT->value,
                    TransactionStatus::POSTED->value,
                ]);
            })
            ->when($excludingSaleReturnId, fn ($query) => $query->where('sale_return_id', '!=', $excludingSaleReturnId))
            ->sum('quantity');
    }

    public function remainingReturnableQuantity(?string $excludingSaleReturnId = null): float
    {
        return max((float) $this->quantity - $this->returnedQuantity($excludingSaleReturnId), 0.0);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Size::class);
    }
}

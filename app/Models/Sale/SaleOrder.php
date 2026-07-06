<?php

namespace App\Models\Sale;

use App\Enums\SaleOrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;

class SaleOrder extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasDynamicFilters, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'number',
        'date',
        'delivery_date',
        'customer_id',
        'currency_id',
        'rate',
        'warehouse_id',
        'discount',
        'discount_type',
        'status',
        'note',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'delivery_date' => 'date',
            'customer_id' => 'string',
            'currency_id' => 'string',
            'rate' => 'float',
            'warehouse_id' => 'string',
            'discount' => 'float',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'number',
            'date',
            'delivery_date',
            'note',
            'status',
        ];
    }

    protected array $allowedFilters = [
        'customer_id',
        'currency_id',
        'warehouse_id',
        'status',
        'date',
        'delivery_date',
        'created_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Ledger\Ledger::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Currency::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleOrderItem::class);
    }

    public function sale(): HasOne
    {
        return $this->hasOne(Sale::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class, 'branch_id');
    }

    public function getDependencyMessage(): string
    {
        return 'You cannot delete this sale order because it has dependencies.';
    }

    public function orderTotal(): float
    {
        $orderAmount = $this->items->sum(function (SaleOrderItem $item) {
            $rowTotal = (float) $item->quantity * (float) $item->unit_price;
            $itemDiscount = (float) ($item->discount ?? 0);

            return $rowTotal - $itemDiscount;
        });

        $orderDiscount = $this->discount_type === 'percentage'
            ? $orderAmount * ((float) ($this->discount ?? 0) / 100)
            : (float) ($this->discount ?? 0);

        return $orderAmount - $orderDiscount;
    }

    public function isDraft(): bool
    {
        return $this->status === SaleOrderStatus::DRAFT->value;
    }

    public function isPosted(): bool
    {
        return $this->status === SaleOrderStatus::POSTED->value;
    }

    public function isCompleted(): bool
    {
        return $this->status === SaleOrderStatus::COMPLETED->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === SaleOrderStatus::CANCELLED->value;
    }
}

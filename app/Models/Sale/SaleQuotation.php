<?php

namespace App\Models\Sale;

use App\Enums\SaleQuotationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

class SaleQuotation extends Model
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
        'valid_until',
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
            'valid_until' => 'date',
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
            'valid_until',
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
        'valid_until',
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
        return $this->hasMany(SaleQuotationItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class, 'branch_id');
    }

    public function getDependencyMessage(): string
    {
        return 'You cannot delete this sale quotation because it has dependencies.';
    }

    public function quotationTotal(): float
    {
        $amount = $this->items->sum(function (SaleQuotationItem $item) {
            $rowTotal = (float) $item->quantity * (float) $item->unit_price;
            $itemDiscount = (float) ($item->discount ?? 0);

            return $rowTotal - $itemDiscount;
        });

        $documentDiscount = $this->discount_type === 'percentage'
            ? $amount * ((float) ($this->discount ?? 0) / 100)
            : (float) ($this->discount ?? 0);

        return $amount - $documentDiscount;
    }

    public function isDraft(): bool
    {
        return $this->status === SaleQuotationStatus::DRAFT->value;
    }

    public function isPosted(): bool
    {
        return $this->status === SaleQuotationStatus::POSTED->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === SaleQuotationStatus::CANCELLED->value;
    }
}

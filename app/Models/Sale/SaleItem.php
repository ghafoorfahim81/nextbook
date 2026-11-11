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
use App\Traits\CompanySpecific;
use App\Traits\HasCompanyFromParent;
use App\Traits\SnapshotsItem;
class SaleItem extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, HasDependencyCheck, SoftDeletes, CompanySpecific, HasCompanyFromParent, SnapshotsItem;

    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'sale_id',
        'item_id',
        'variant_id',
        'batch',
        'expire_date',
        'quantity',
        'unit_measure_id',
        'unit_price',
        'discount',
        'net_unit_cost',
        'free',
        'tax',
        'warehouse_id',
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
            'sale_id' => 'string',
            'item_id' => 'string',
            'variant_id' => 'string',
            'batch' => 'string',
            'expire_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_measure_id' => 'string',
            // Four decimals in the column; see PurchaseItem.
            'unit_price' => 'float',
            'net_unit_cost' => \App\Casts\StockCost::class,
            // Four decimals in the columns; see Item.
            'discount' => 'float',
            'free' => 'float',
            'tax' => 'float',
            'warehouse_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
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

    public function variant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\ItemVariant::class, 'variant_id');
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
        return max($this->deliveredQuantity() - $this->returnedQuantity($excludingSaleReturnId), 0.0);
    }

    /** Every unit the line handed over: the ones charged for and the free ones. */
    public function deliveredQuantity(): float
    {
        return (float) $this->quantity + max(0.0, (float) ($this->free ?? 0));
    }

    /**
     * What one delivered unit of this line was really charged at, split into
     * the price before discount and the discount on it.
     *
     * A return has to give back what the customer paid, not the list price:
     * the line's own discount and its share of the bill discount both come
     * off. Free units were delivered at no charge, so they dilute the figure
     * (45 sold + 5 free at 40 is 50 units at 36), which is what lets a free
     * unit be returned at all without crediting money that was never taken.
     *
     * @return array{gross: float, discount: float, net: float}  Per delivered unit, sale currency.
     */
    public function returnUnitValue(): array
    {
        $delivered = $this->deliveredQuantity();

        if ($delivered <= 0) {
            return ['gross' => 0.0, 'discount' => 0.0, 'net' => 0.0];
        }

        $sale = $this->sale;
        $lineGross = money_round((float) $this->quantity * (float) $this->unit_price);
        $billDiscount = 0.0;

        if ($sale && (float) $sale->discount > 0) {
            if ($sale->discount_type === 'percentage') {
                $billDiscount = $lineGross * ((float) $sale->discount / 100);
            } else {
                // A fixed bill discount is shared out by each line's value.
                $saleGross = (float) $sale->items->sum(fn ($item) => money_round((float) $item->quantity * (float) $item->unit_price));
                $billDiscount = $saleGross > 0 ? (float) $sale->discount * ($lineGross / $saleGross) : 0.0;
            }
        }

        $discount = min($lineGross, money_round((float) ($this->discount ?? 0) + $billDiscount));

        return [
            'gross' => $lineGross / $delivered,
            'discount' => $discount / $delivered,
            'net' => ($lineGross - $discount) / $delivered,
        ];
    }

    /** No branch of its own: the sale holds it, and the company comes from there too. */
    protected function companyParentRelation(): string
    {
        return 'sale';
    }
}

<?php

namespace App\Models\Purchase;

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
class PurchaseItem extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, CompanySpecific, HasDependencyCheck, SoftDeletes, HasCompanyFromParent, SnapshotsItem;

    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'purchase_id',
        'item_id',
        'variant_id',
        'batch',
        'expire_date',
        'quantity',
        'unit_measure_id',
        'warehouse_id',
        'unit_price',
        'net_unit_cost',
        'discount',
        'free',
        'tax',
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
            'purchase_id' => 'string',
            'item_id' => 'string',
            'variant_id' => 'string',
            'batch' => 'string',
            'expire_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_measure_id' => 'string',
            'warehouse_id' => 'string',
            // Four decimals in the column: a 2-decimal cast read a dollar price
            // of 0.1538 back as 0.15, and returns and landed costs used that.
            'unit_price' => 'float',
            'net_unit_cost' => \App\Casts\StockCost::class,
            'discount' => 'float',
            'free' => 'float',
            'tax' => 'float',
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
            'purchase.number',
            'item.name',
            'item.code',
            'unitMeasure.name',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Item::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\ItemVariant::class, 'variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class, 'warehouse_id');
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\UnitMeasure::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    /**
     * Sum of quantity already claimed by non-reversed purchase returns for this line.
     * Includes DRAFT returns so two concurrent drafts cannot both claim the same units.
     */
    public function returnedQuantity(?string $excludingPurchaseReturnId = null): float
    {
        return (float) $this->returnItems()
            ->whereHas('purchaseReturn', function ($query) {
                $query->whereIn('status', [
                    TransactionStatus::DRAFT->value,
                    TransactionStatus::POSTED->value,
                ]);
            })
            ->when($excludingPurchaseReturnId, fn ($query) => $query->where('purchase_return_id', '!=', $excludingPurchaseReturnId))
            ->sum('quantity');
    }

    public function remainingReturnableQuantity(?string $excludingPurchaseReturnId = null): float
    {
        return max($this->deliveredQuantity() - $this->returnedQuantity($excludingPurchaseReturnId), 0.0);
    }

    /** Every unit the line brought in: the ones paid for and the free ones. */
    public function deliveredQuantity(): float
    {
        return (float) $this->quantity + max(0.0, (float) ($this->free ?? 0));
    }

    /** deliveredQuantity() in the item's own unit. */
    public function deliveredBaseQuantity(): float
    {
        $quantity = (float) $this->quantity;

        return $quantity > 0
            ? $this->baseQuantity() * ($this->deliveredQuantity() / $quantity)
            : $this->deliveredQuantity();
    }

    /**
     * What one delivered unit of this line really cost, in the purchase's
     * currency: its net value (after its discount and its share of the bill
     * discount, plus its tax) spread over the paid and the free units alike.
     *
     * This is what the stock was received at, what a return sends back and
     * what the supplier is debited for it.
     */
    public function netUnitValue(): float
    {
        $delivered = $this->deliveredQuantity();
        $purchase = $this->purchase;

        if ($delivered <= 0 || ! $purchase) {
            return 0.0;
        }

        $nets = Purchase::lineNetValues($purchase->items, $purchase->discount, $purchase->discount_type);

        // The line's tax was added to the cost of its goods, so it goes back too.
        return (($nets[$this->id] ?? 0.0) + (float) ($this->tax ?? 0)) / $delivered;
    }

    /**
     * The same, in home currency: what the stock layer was costed at. Stored
     * on the line when it is posted; worked out for lines written before.
     */
    public function stockUnitCost(): float
    {
        if ($this->net_unit_cost !== null && (float) $this->net_unit_cost > 0) {
            return (float) $this->net_unit_cost;
        }

        $rate = max((float) ($this->purchase?->transaction?->rate ?: 1), 0.0000001);

        return $this->netUnitValue() * $rate;
    }

    /**
     * The variant's name, when it tells this line apart from the item itself.
     *
     * Every item has at least one variant, and an unnamed one reads as the
     * item's own name. Showing that next to the item would only repeat it.
     */
    public function distinctVariantName(): ?string
    {
        $name = $this->snapshotVariantName();

        return filled($name) && $name !== $this->snapshotItemName() ? $name : null;
    }

    /**
     * This line's quantity in the item's own unit: 2 boxes of 12 is 24 pieces.
     *
     * Looked up with trashed units, because a posted bill can name a unit that
     * has since been retired.
     */
    public function baseQuantity(): float
    {
        $unit = fn (?string $id): float => $id
            ? (float) \App\Models\Administration\UnitMeasure::withTrashed()->whereKey($id)->value('unit')
            : 0.0;

        $itemUnit = $unit($this->item?->unit_measure_id) ?: 1.0;
        $lineUnit = $unit($this->unit_measure_id) ?: $itemUnit;

        return (float) $this->quantity * ($lineUnit / $itemUnit);
    }

    /** No branch of its own: the purchase holds it, and the company comes from there too. */
    protected function companyParentRelation(): string
    {
        return 'purchase';
    }
}

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
            'unit_price' => 'decimal:2',
            'net_unit_cost' => \App\Casts\StockCost::class,
            'discount' => 'decimal:2',
            'free' => 'decimal:2',
            'tax' => 'decimal:2',
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
        return max((float) $this->quantity - $this->returnedQuantity($excludingPurchaseReturnId), 0.0);
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

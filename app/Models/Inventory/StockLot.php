<?php

namespace App\Models\Inventory;

use App\Enums\LotStatus;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One delivery of stock sharing the same characteristics.
 *
 * A lot may have a batch number, an expiry date, or both. Expiry-only lots are
 * the common case in food retail — milk and bread carry a printed best-before
 * date and no batch code at all.
 */
class StockLot extends Model
{
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasSearch,
        HasSorting, HasBranch, BranchSpecific, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'item_id',
        'variant_id',
        'batch_no',
        'expire_date',
        'manufacture_date',
        'mrp',
        'sale_price',
        'unit_cost',
        'purchase_price',
        'received_date',
        'status',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'item_id' => 'string',
            'variant_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'expire_date' => 'date',
            'manufacture_date' => 'date',
            'received_date' => 'date',
            'mrp' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'unit_cost' => 'decimal:4',
            'purchase_price' => 'decimal:4',
            'status' => LotStatus::class,
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['batch_no', 'item.name', 'item.code'];
    }

    /**
     * What the storekeeper sees. A pharmacist reads "B-1043", a baker reads
     * the expiry, because that is the only identifier their stock carries.
     */
    public function label(): string
    {
        if (filled($this->batch_no)) {
            return $this->batch_no;
        }

        return $this->expire_date?->format('Y-m-d') ?? '—';
    }

    public function isExpired(): bool
    {
        return $this->expire_date !== null && $this->expire_date->isPast();
    }

    public function expiresWithin(int $days): bool
    {
        if ($this->expire_date === null) {
            return false;
        }

        return $this->expire_date->between(Carbon::today(), Carbon::today()->addDays($days));
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'variant_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'lot_id');
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(StockPiece::class, 'lot_id');
    }

    /**
     * On-hand across every warehouse holding this lot.
     */
    public function onHand(): float
    {
        return (float) $this->balances()->sum('quantity');
    }

    protected function getRelationships(): array
    {
        return [
            'stock_balances' => [
                'model' => 'stock_balances',
                'message' => 'This lot has stock balances',
            ],
        ];
    }
}

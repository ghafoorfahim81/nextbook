<?php

namespace App\Models\Purchase;

use App\Enums\SalePurchaseType;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasDynamicFilters;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\BranchSpecific;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Transaction\Transaction;
use App\Traits\HasUserTracking;
class Purchase extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasDynamicFilters, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'number',
        'supplier_id',
        'date',
        'discount',
        'discount_type',
        'bank_account_id',
        'type',
        'due_date',
        'description',
        'status',
        'payment_status',
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
            'supplier_id' => 'string',
            'date' => 'date',
            'discount' => 'float',
            'bank_account_id' => 'string',
        'type' => SalePurchaseType::class,
        'payment_status' => PaymentStatus::class,
        'created_by' => 'string',
            'updated_by' => 'string',
            'due_date' => 'date',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'number',
            'date',
            'discount',
            'discount_type',
            'type',
            'due_date',
            'description',
            'status',
        ];
    }

    protected array $allowedFilters = [
        'supplier_id',
        'transaction.currency_id',
        'type',
        'warehouse_id',
        'date',
        'due_date',
        'created_by',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Ledger\Ledger::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'reference_id');
    }

    public function items()
    {
        return $this->hasMany(\App\Models\Purchase\PurchaseItem::class);
    }

    public function landedCosts(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Inventory\LandedCost::class, 'landed_cost_purchases')
            ->withTimestamps()
            ->withPivot('id');
    }

    public function purchasePayments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    public function getDependencyMessage(): string
    {
        return 'You cannot delete this purchase because it has dependencies.';
    }

    public function stocks()
    {
        return $this->hasMany(\App\Models\Inventory\StockMovement::class, 'reference_id', 'id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account\Account::class);
    }

    public function warehouse()
    {
        return $this->items?->first()?->warehouse;
    }

}

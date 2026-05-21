<?php

namespace App\Models\Sale;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
class Sale extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasDynamicFilters, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'number',
        'customer_id',
        'date',
        'discount',
        'discount_type',
        'currency_id',
        'rate',
        'initial_receipt_amount',
        'initial_receipt_account_id',
        'type',
        'due_date',
        'description',
        'status',
        'payment_status',
        'posted_by',
        'posted_at',
        'reversal_of_id',
        'reversed_at',
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
            'customer_id' => 'string',
            'date' => 'date',
            'discount' => 'float',
            'currency_id' => 'string',
            'rate' => 'float',
            'initial_receipt_amount' => 'float',
            'initial_receipt_account_id' => 'string',
            'status' => \App\Enums\TransactionStatus::class,
            'payment_status' => PaymentStatus::class,
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
            'posted_by' => 'string',
            'reversal_of_id' => 'string',
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
        'customer_id',
        'transaction.currency_id',
        'type',
        'warehouse_id',
        'date',
        'due_date',
        'created_by',
    ];

    protected function dynamicFilterHandlers(): array
    {
        return [
            'warehouse_id' => function ($query, $value) {
                $query->whereHas('items', function ($itemQuery) use ($value) {
                    if (is_array($value)) {
                        $itemQuery->whereIn('warehouse_id', $value);
                        return;
                    }

                    $itemQuery->where('warehouse_id', $value);
                });
            },
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Ledger\Ledger::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'reference_id');
    }

    public function items()
    {
        return $this->hasMany(\App\Models\Sale\SaleItem::class);
    }

    public function saleReceives(): HasMany
    {
        return $this->hasMany(SaleReceive::class);
    }

    public function stockOuts()
    {
        return $this->hasMany(\App\Models\Inventory\StockOut::class, 'source_id', 'id');
    }

    public function getDependencyMessage(): string
    {
        return 'You cannot delete this sale because it has dependencies.';
    }
    public function warehouse()
    {
        return $this->items?->first()?->warehouse;
    }
}

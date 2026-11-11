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
use App\Traits\HasAttachments;
use App\Traits\HasBranch;
use App\Traits\HasCompany;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasDynamicFilters;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\BranchSpecific;
use App\Traits\CompanySpecific;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Transaction\Transaction;
use App\Traits\HasUserTracking;
use App\Models\Concerns\HasSequentialNumber;
class Purchase extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasDynamicFilters, HasUserAuditable, CompanySpecific, BranchSpecific, HasCompany, HasBranch, HasDependencyCheck, HasAttachments, SoftDeletes, HasSequentialNumber;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'number',
        'supplier_id',
        'purchase_order_id',
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
            'purchase_order_id' => 'string',
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
            'payment_status',
            'supplier.name',
            'transaction.currency.name',
        ];
    }

    protected array $allowedFilters = [
        'supplier_id',
        'transaction.currency_id',
        'type',
        'warehouse_id',
        'payment_status',
        'date',
        'due_date',
        'created_by',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Ledger\Ledger::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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

    /**
     * Posted purchase bills that can still carry a landed cost.
     *
     * Draft and reversed bills hold no stock to add cost to. A bill whose
     * landed cost was reversed is free again, so only live (non-reversed)
     * landed costs count as taking it; the one being edited never does.
     */
    public function scopeAvailableForLandedCost($query, ?string $exceptLandedCostId = null)
    {
        return $query
            ->where('status', \App\Enums\TransactionStatus::POSTED->value)
            ->whereDoesntHave('landedCosts', function ($landedCosts) use ($exceptLandedCostId): void {
                $landedCosts->where('landed_costs.status', '!=', \App\Enums\LandedCostStatus::Reversed->value);

                if ($exceptLandedCostId) {
                    $landedCosts->whereKeyNot($exceptLandedCostId);
                }
            });
    }

    /**
     * What the bill comes to: goods − line discounts − bill discount + tax.
     *
     * The sum of the lines' net values, so it is always exactly what the
     * ledger was posted with. A fixed bill discount comes off once — this used
     * to take it off every line. Requires the `items` relation loaded.
     */
    public function purchaseTotal(): float
    {
        return money_round(
            array_sum(static::lineNetValues($this->items, $this->discount, $this->discount_type))
                + (float) $this->items->sum(fn ($item) => (float) ($item->tax ?? 0))
        );
    }

    /**
     * Each line's net value in the purchase's currency, keyed by line id (or
     * position, for lines not saved yet): quantity × price, less its own
     * discount and its share of the bill discount.
     *
     * One formula for posting, returns, landed costs and every total shown, so
     * none of them can disagree. A percentage bill discount is taken on each
     * line's value of goods; a fixed one is shared out by that value.
     *
     * @param  iterable<int, mixed>  $lines  Models or arrays with quantity, unit_price, discount.
     * @return array<string|int, float>
     */
    public static function lineNetValues(iterable $lines, mixed $billDiscount, ?string $billDiscountType): array
    {
        $rows = [];
        $grossTotal = 0.0;

        foreach ($lines as $key => $line) {
            // At cents: 49 kg at 71.4286 is 3,500.00, not 3,500.0014.
            $gross = money_round((float) data_get($line, 'quantity', 0) * (float) data_get($line, 'unit_price', 0));
            $rows[data_get($line, 'id') ?? $key] = [
                'gross' => $gross,
                'discount' => (float) (data_get($line, 'discount') ?? 0),
            ];
            $grossTotal += $gross;
        }

        $discount = max(0.0, (float) $billDiscount);
        $nets = [];

        foreach ($rows as $key => $row) {
            $share = ($billDiscountType ?? 'percentage') === 'percentage'
                ? $row['gross'] * ($discount / 100)
                : ($grossTotal > 0 ? $discount * ($row['gross'] / $grossTotal) : 0.0);

            $nets[$key] = money_round($row['gross'] - $row['discount'] - money_round($share));
        }

        return $nets;
    }

    /**
     * Settlements against this purchase's payable line. Mirror of
     * Sale::settlements().
     */
    public function settlements()
    {
        return \App\Models\Accounting\Settlement::query()
            ->whereIn('target_line_id', function ($query) {
                $query->select('tl.id')
                    ->from('transaction_lines as tl')
                    ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
                    ->where('t.reference_type', self::class)
                    ->where('t.reference_id', $this->id)
                    ->whereNull('t.deleted_at')
                    ->whereNull('tl.deleted_at');
            });
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    public function purchaseReturnItems(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            PurchaseReturnItem::class,
            PurchaseItem::class,
            'purchase_id',
            'purchase_item_id',
            'id',
            'id'
        );
    }

    public function getDependencyMessage(): string
    {
        return __('messages.dependency.cannot_delete_document', ['document' => __('messages.dependency.documents.purchase')]);
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

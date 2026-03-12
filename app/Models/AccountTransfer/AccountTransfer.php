<?php

namespace App\Models\AccountTransfer;

use App\Models\Transaction\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasBranch;
use App\Traits\HasDynamicFilters;
use App\Traits\HasUserAuditable;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\BranchSpecific;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Builder;
class AccountTransfer extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, HasUserTracking, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'number',
        'date',
        'transaction_id',
        'remark',
        'branch_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id' => 'string',
        'date' => 'date',
        'transaction_id' => 'string',
        'branch_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'deleted_by' => 'string',
    ];

    protected array $allowedFilters = [
        // Filters
        'from_account_id',
        'to_account_id',
        'transaction.currency_id',
        'amount',
        // Native columns
        'date',
        // Common
        'created_by',
    ];

    /**
     * Custom filter handlers for virtual fields.
     *
     * @return array<string, callable(Builder, mixed, array): void>
     */
    protected function dynamicFilterHandlers(): array
    {
        return [
            'from_account_id' => function (Builder $query, mixed $value): void {
                $query->whereHas('transaction.lines', function (Builder $q) use ($value) {
                    $q->where('account_id', $value);
                });
            },
            'to_account_id' => function (Builder $query, mixed $value): void {
                $query->whereHas('transaction.lines', function (Builder $q) use ($value) {
                    $q->where('account_id', $value);
                });
            },
            'amount_min' => function (Builder $query, mixed $value): void {
                if (!is_numeric($value)) {
                    return;
                }
                $query->whereHas('transaction.lines', function (Builder $q) use ($value) {
                    $q->where(function (Builder $w) use ($value) {
                        $w->where('debit', '>=', $value)->orWhere('credit', '>=', $value);
                    });
                });
            },
            'amount_max' => function (Builder $query, mixed $value): void {
                if (!is_numeric($value)) {
                    return;
                }
                $query->whereHas('transaction.lines', function (Builder $q) use ($value) {
                    $q->where(function (Builder $w) use ($value) {
                        $w->where('debit', '<=', $value)->orWhere('credit', '<=', $value);
                    });
                });
            },
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

}



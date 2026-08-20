<?php

namespace App\Models\Hr;

use App\Enums\PaymentMode;
use App\Models\Account\Account;
use App\Models\Accounting\Settlement;
use App\Models\Administration\Currency;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money actually leaving the company for salary.
 *
 * Named salary_payments because `payments` is already the supplier voucher.
 * Posts through SettlementService rather than TransactionService directly, so
 * it relieves the per-employee credits the payroll accrual left on Payroll
 * Liabilities — which brings partial payment, FX realisation and overpayment
 * handling along for free.
 */
class SalaryPayment extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasSequentialNumber, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'number', 'date', 'payroll_id', 'employee_id', 'ledger_id',
        'currency_id', 'rate', 'amount', 'payment_mode', 'bank_account_id',
        'cheque_no', 'transaction_id', 'narration', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'payroll_id' => 'string',
            'employee_id' => 'string',
            'ledger_id' => 'string',
            'currency_id' => 'string',
            'bank_account_id' => 'string',
            'transaction_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'date' => 'date',
            'rate' => 'decimal:8',
            'amount' => 'decimal:4',
            'payment_mode' => PaymentMode::class,
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['number', 'cheque_no', 'narration', 'employee.full_name', 'employee.code'];
    }

    protected array $allowedFilters = [
        'payroll_id', 'employee_id', 'payment_mode', 'date', 'created_by',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalaryPaymentLine::class);
    }

    /**
     * Settlement rows produced by this voucher, reached through its
     * transaction — the same shape Payment::settlements() uses.
     */
    public function settlements(): HasManyThrough
    {
        return $this->hasManyThrough(
            Settlement::class,
            Transaction::class,
            'id',
            'transaction_id',
            'transaction_id',
            'id'
        );
    }
}

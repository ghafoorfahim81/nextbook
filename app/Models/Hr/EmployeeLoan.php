<?php

namespace App\Models\Hr;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\Transaction\Transaction;
use App\Traits\BranchSpecific;
use App\Traits\HasAttachments;
use App\Traits\HasBranch;
use App\Traits\HasBranch as HasBranchTrait;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A staff loan or advance, recovered through payroll.
 *
 * `outstanding_amount` is denormalised and recomputed from repayments on every
 * write. Payroll reads it once per employee per run; a SUM subquery there would
 * be an N+1 across the whole workforce.
 */
class EmployeeLoan extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasAttachments, HasSequentialNumber, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'number', 'employee_id', 'loan_type', 'currency_id', 'rate',
        'principal_amount', 'installment_amount', 'installments_count',
        'deduct_from_payroll', 'issue_date', 'first_deduction_period',
        'interest_rate', 'outstanding_amount', 'status', 'bank_account_id',
        'approved_by', 'approved_at', 'transaction_id', 'remark',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'employee_id' => 'string',
            'currency_id' => 'string',
            'bank_account_id' => 'string',
            'transaction_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'loan_type' => LoanType::class,
            'status' => LoanStatus::class,
            'issue_date' => 'date',
            'first_deduction_period' => 'date',
            'approved_at' => 'datetime',
            'rate' => 'decimal:8',
            'principal_amount' => 'decimal:4',
            'installment_amount' => 'decimal:4',
            'outstanding_amount' => 'decimal:4',
            'interest_rate' => 'decimal:4',
            'installments_count' => 'integer',
            'deduct_from_payroll' => 'boolean',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['number', 'remark', 'employee.full_name', 'employee.code'];
    }

    protected array $allowedFilters = [
        'employee_id', 'loan_type', 'status', 'issue_date', 'created_by',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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

    public function repayments(): HasMany
    {
        return $this->hasMany(EmployeeLoanRepayment::class);
    }

    public function typeEnum(): LoanType
    {
        return $this->loan_type instanceof LoanType
            ? $this->loan_type
            : (LoanType::tryFrom((string) $this->loan_type) ?? LoanType::Advance);
    }

    public function statusEnum(): LoanStatus
    {
        return $this->status instanceof LoanStatus
            ? $this->status
            : (LoanStatus::tryFrom((string) $this->status) ?? LoanStatus::Draft);
    }

    /** The GL control account this balance lives in. */
    public function accountSlug(): string
    {
        return $this->typeEnum()->accountSlug();
    }

    /**
     * Recompute the running balance from actual repayments.
     *
     * Derived rather than decremented, so a reversed payroll deleting its
     * repayment rows automatically restores the balance without anyone having
     * to remember to add it back.
     */
    public function refreshOutstanding(): self
    {
        $repaid = (string) $this->repayments()->sum('amount');
        $outstanding = Decimal::sub(
            Decimal::amount($this->principal_amount),
            Decimal::amount($repaid)
        );

        if (Decimal::cmp($outstanding, '0') < 0) {
            $outstanding = '0.0000';
        }

        $attributes = ['outstanding_amount' => $outstanding];

        // A fully repaid loan settles itself; an over-corrected one becomes
        // active again so the next run keeps recovering.
        if ($this->statusEnum()->isDisbursed()) {
            $attributes['status'] = Decimal::isZero($outstanding)
                ? LoanStatus::Settled->value
                : LoanStatus::Active->value;
        }

        $this->forceFill($attributes)->save();

        return $this;
    }

    /**
     * What this run should recover: the instalment, capped at what is left.
     */
    public function installmentDue(): string
    {
        $outstanding = Decimal::amount($this->outstanding_amount);
        $installment = Decimal::amount($this->installment_amount);

        if (! $this->deduct_from_payroll || ! $this->statusEnum()->isRecoverable()) {
            return '0.0000';
        }

        return Decimal::cmp($installment, $outstanding) > 0 ? $outstanding : $installment;
    }

    /** Loans payroll should recover from on a given date. */
    public function scopeRecoverableOn($query, string $date)
    {
        return $query
            ->where('status', LoanStatus::Active->value)
            ->where('deduct_from_payroll', true)
            ->where('outstanding_amount', '>', 0)
            ->where(function ($q) use ($date) {
                $q->whereNull('first_deduction_period')
                    ->orWhereDate('first_deduction_period', '<=', $date);
            });
    }
}

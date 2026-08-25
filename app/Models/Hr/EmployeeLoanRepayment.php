<?php

namespace App\Models\Hr;

use App\Enums\LoanRepaymentSource;
use App\Models\Administration\Currency;
use App\Models\Transaction\Transaction;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeLoanRepayment extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'employee_loan_id', 'payroll_line_id', 'salary_payment_id',
        'date', 'amount', 'currency_id', 'rate', 'source',
        'transaction_id', 'remark', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'employee_loan_id' => 'string',
            'payroll_line_id' => 'string',
            'salary_payment_id' => 'string',
            'currency_id' => 'string',
            'transaction_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'date' => 'date',
            'amount' => 'decimal:4',
            'rate' => 'decimal:8',
            'source' => LoanRepaymentSource::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(EmployeeLoan::class, 'employee_loan_id');
    }

    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class, 'payroll_line_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}

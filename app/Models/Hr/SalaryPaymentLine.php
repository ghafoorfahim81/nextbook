<?php

namespace App\Models\Hr;

use App\Models\Administration\Currency;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Which payslip a salary payment settled, and for how much.
 *
 * Kept alongside the settlement rows because a payment can cover several
 * payslips at once, and the payroll register needs to show what each employee
 * received without walking the GL.
 */
class SalaryPaymentLine extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'salary_payment_id', 'payroll_line_id', 'employee_id',
        'amount', 'currency_id', 'rate', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'salary_payment_id' => 'string',
            'payroll_line_id' => 'string',
            'employee_id' => 'string',
            'currency_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'amount' => 'decimal:4',
            'rate' => 'decimal:8',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SalaryPayment::class, 'salary_payment_id');
    }

    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class, 'payroll_line_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}

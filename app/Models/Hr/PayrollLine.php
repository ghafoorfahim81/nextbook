<?php

namespace App\Models\Hr;

use App\Enums\PayrollLinePaymentStatus;
use App\Models\Administration\Currency;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One payslip.
 *
 * Carries a base-currency snapshot frozen at calculation time so a mixed
 * AFN/USD run can be summed for reporting without re-rating, and so a reprint
 * two years later shows the figure that was actually posted rather than
 * today's exchange rate applied to yesterday's salary.
 */
class PayrollLine extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'payroll_id', 'employee_id', 'salary_structure_id', 'tax_bracket_set_id',
        'currency_id', 'rate',
        'working_days', 'present_days', 'absent_days', 'paid_leave_days',
        'unpaid_leave_days', 'overtime_hours',
        'basic_salary', 'gross_earnings', 'total_deductions', 'taxable_income',
        'tax_amount', 'net_payable', 'base_gross', 'base_net',
        'paid_amount', 'payment_status', 'remark', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'payroll_id' => 'string',
            'employee_id' => 'string',
            'salary_structure_id' => 'string',
            'tax_bracket_set_id' => 'string',
            'currency_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'rate' => 'decimal:8',
            'working_days' => 'decimal:2',
            'present_days' => 'decimal:2',
            'absent_days' => 'decimal:2',
            'paid_leave_days' => 'decimal:2',
            'unpaid_leave_days' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'basic_salary' => 'decimal:4',
            'gross_earnings' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'taxable_income' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'net_payable' => 'decimal:4',
            'base_gross' => 'decimal:4',
            'base_net' => 'decimal:4',
            'paid_amount' => 'decimal:4',
            'payment_status' => PayrollLinePaymentStatus::class,
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['employee.full_name', 'employee.code'];
    }

    protected array $allowedFilters = ['payroll_id', 'employee_id', 'payment_status'];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function taxBracketSet(): BelongsTo
    {
        return $this->belongsTo(TaxBracketSet::class, 'tax_bracket_set_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(PayrollLineComponent::class)->orderBy('sequence');
    }

    public function loanRepayments(): HasMany
    {
        return $this->hasMany(EmployeeLoanRepayment::class);
    }

    /** What is still owed on this payslip. */
    public function outstanding(): float
    {
        return round((float) $this->net_payable - (float) $this->paid_amount, 4);
    }
}

<?php

namespace App\Models\Hr;

use App\Enums\ComponentCalculationType;
use App\Enums\SalaryComponentType;
use App\Models\Account\Account;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One line of a payslip breakdown.
 *
 * The component's code, name, type and GL account are SNAPSHOTTED here rather
 * than read through the relation. A payslip reprinted two years later has to
 * show what was paid, under the name it was paid under — not what the component
 * happens to be called today, and not a blank because it was since deleted.
 */
class PayrollLineComponent extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'payroll_line_id', 'salary_component_id',
        'component_code', 'component_name', 'component_type', 'calculation_type',
        'rate_or_percentage', 'base_amount', 'amount',
        'is_taxable', 'account_id', 'sequence', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'payroll_line_id' => 'string',
            'salary_component_id' => 'string',
            'account_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'component_type' => SalaryComponentType::class,
            'calculation_type' => ComponentCalculationType::class,
            'rate_or_percentage' => 'decimal:8',
            'base_amount' => 'decimal:4',
            'amount' => 'decimal:4',
            'is_taxable' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class, 'payroll_line_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function isEarning(): bool
    {
        return $this->component_type === SalaryComponentType::Earning;
    }
}

<?php

namespace App\Models\Hr;

use App\Enums\ComponentCalculationType;
use App\Enums\SalaryComponentType;
use App\Models\Account\Account;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
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
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryComponent extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    /** Wage tax withheld from the employee. Created by payroll, never by hand. */
    public const CODE_WAGE_TAX = 'WAGE_TAX';

    /** Salary docked for leave the employee had no entitlement for. */
    public const CODE_UNPAID_LEAVE = 'UNPAID_LEAVE';

    /** Loan or advance instalment recovered this run. */
    public const CODE_LOAN_RECOVERY = 'LOAN_RECOVERY';

    /** Overtime paid above the shift full-day hours. */
    public const CODE_OVERTIME = 'OVERTIME';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name', 'code', 'component_type', 'calculation_type', 'amount', 'percentage',
        'is_taxable', 'affects_gross', 'is_prorated', 'account_id', 'sequence',
        'is_system', 'is_active', 'remark', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'account_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'component_type' => SalaryComponentType::class,
            'calculation_type' => ComponentCalculationType::class,
            'amount' => 'decimal:4',
            'percentage' => 'decimal:4',
            'is_taxable' => 'boolean',
            'affects_gross' => 'boolean',
            'is_prorated' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['name', 'code', 'remark'];
    }

    protected array $allowedFilters = ['component_type', 'calculation_type', 'is_active', 'is_system'];

    protected function getRelationships(): array
    {
        return [
            'structureLines' => [
                'model' => 'salary_structure_lines',
                'message' => 'This component is used in salary structures',
            ],
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function structureLines(): HasMany
    {
        return $this->hasMany(SalaryStructureLine::class);
    }

    /**
     * Components payroll creates for itself.
     *
     * Seeded per branch so they always exist with a stable code — the
     * calculation looks them up by code, never by name.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultComponents(): array
    {
        return [
            [
                'name' => 'Overtime',
                'code' => self::CODE_OVERTIME,
                'component_type' => SalaryComponentType::Earning->value,
                'calculation_type' => ComponentCalculationType::PerHour->value,
                'is_taxable' => true,
                'is_prorated' => false,
                'is_system' => true,
                'sequence' => 50,
            ],
            [
                'name' => 'Unpaid Leave',
                'code' => self::CODE_UNPAID_LEAVE,
                'component_type' => SalaryComponentType::Deduction->value,
                'calculation_type' => ComponentCalculationType::PerDay->value,
                'is_taxable' => false,
                'is_prorated' => false,
                'is_system' => true,
                'sequence' => 80,
            ],
            [
                'name' => 'Loan Recovery',
                'code' => self::CODE_LOAN_RECOVERY,
                'component_type' => SalaryComponentType::Deduction->value,
                'calculation_type' => ComponentCalculationType::Fixed->value,
                'is_taxable' => false,
                'is_prorated' => false,
                'is_system' => true,
                'sequence' => 90,
            ],
            [
                'name' => 'Wage Withholding Tax',
                'code' => self::CODE_WAGE_TAX,
                'component_type' => SalaryComponentType::Deduction->value,
                'calculation_type' => ComponentCalculationType::Fixed->value,
                'is_taxable' => false,
                'is_prorated' => false,
                'is_system' => true,
                'sequence' => 100,
            ],
        ];
    }
}

<?php

namespace App\Models\Hr;

use App\Enums\PayFrequency;
use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Administration\Designation;
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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * An employee's pay package, effective-dated.
 *
 * A raise is a NEW structure with a later effective_from, not an edit to the
 * old one — so re-running a past period still resolves the salary that applied
 * at the time.
 */
class SalaryStructure extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name', 'code', 'employee_id', 'designation_id', 'department_id',
        'currency_id', 'effective_from', 'effective_to', 'basic_salary',
        'pay_frequency', 'expense_account_id', 'is_active', 'remark',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'employee_id' => 'string',
            'designation_id' => 'string',
            'department_id' => 'string',
            'currency_id' => 'string',
            'expense_account_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'basic_salary' => 'decimal:4',
            'pay_frequency' => PayFrequency::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['name', 'code', 'employee.full_name', 'employee.code'];
    }

    protected array $allowedFilters = [
        'employee_id', 'designation_id', 'department_id',
        'pay_frequency', 'is_active', 'effective_from',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalaryStructureLine::class);
    }

    /**
     * The structure in force for an employee on a given date.
     *
     * Falls back to a template attached to the employee's designation, then to
     * one on their department — so a whole grade can be defined once rather
     * than copied onto every person.
     */
    public static function resolveFor(Employee $employee, Carbon $asOf): ?self
    {
        $inForce = fn ($query) => $query
            ->whereDate('effective_from', '<=', $asOf->toDateString())
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $asOf->toDateString());
            })
            ->where('is_active', true)
            ->orderByDesc('effective_from');

        $personal = static::query()->where('employee_id', $employee->id)->tap($inForce)->first();

        if ($personal) {
            return $personal;
        }

        if ($employee->designation_id) {
            $byDesignation = static::query()
                ->whereNull('employee_id')
                ->where('designation_id', $employee->designation_id)
                ->tap($inForce)
                ->first();

            if ($byDesignation) {
                return $byDesignation;
            }
        }

        if ($employee->department_id) {
            return static::query()
                ->whereNull('employee_id')
                ->whereNull('designation_id')
                ->where('department_id', $employee->department_id)
                ->tap($inForce)
                ->first();
        }

        return null;
    }
}

<?php

namespace App\Models\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PaymentMode;
use App\Models\Administration\Country;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Administration\Designation;
use App\Models\Administration\Province;
use App\Models\Ledger\Ledger;
use App\Models\User;
use App\Traits\BranchSpecific;
use App\Traits\HasAttachments;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A person the company employs.
 *
 * Employees are deliberately split in two: the HR record lives here, and the
 * financial party lives in `ledgers` as an EMPLOYEE-type row created by
 * EmployeeObserver. That split buys salary payable balances, advances,
 * statements and open-item settlement for free from machinery customers and
 * suppliers already use, without pushing thirty HR columns onto the shared
 * `ledgers` table or letting staff leak into commercial party lists.
 */
class Employee extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasDependencyCheck, HasAttachments, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code',
        'ledger_id',
        'user_id',
        'first_name',
        'last_name',
        'father_name',
        'grand_father_name',
        'full_name',
        'gender',
        'marital_status',
        'date_of_birth',
        'national_id',
        'passport_number',
        'tin',
        'country_id',
        'province_id',
        'blood_group',
        'phone_number',
        'alternate_phone',
        'whatsapp_number',
        'email',
        'present_address',
        'permanent_address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'photo',
        'department_id',
        'designation_id',
        'reports_to_id',
        'employment_type',
        'employment_status',
        'joining_date',
        'probation_end_date',
        'confirmation_date',
        'separation_date',
        'separation_reason',
        'currency_id',
        'basic_salary',
        'payment_method',
        'bank_name',
        'bank_account_number',
        'bank_account_title',
        'iban',
        'is_tax_exempt',
        'self_service_enabled',
        'is_active',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'ledger_id' => 'string',
            'user_id' => 'string',
            'country_id' => 'string',
            'province_id' => 'string',
            'department_id' => 'string',
            'designation_id' => 'string',
            'reports_to_id' => 'string',
            'currency_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'gender' => Gender::class,
            'marital_status' => MaritalStatus::class,
            'employment_type' => EmploymentType::class,
            'employment_status' => EmploymentStatus::class,
            'payment_method' => PaymentMode::class,
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'probation_end_date' => 'date',
            'confirmation_date' => 'date',
            'separation_date' => 'date',
            'basic_salary' => 'decimal:4',
            'is_tax_exempt' => 'boolean',
            'self_service_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // full_name is derived, never entered. Doing it on `saving` rather than
        // in the controller means the importer, seeders and tinker all produce
        // the same value the ledger name is mirrored from.
        static::saving(function (Employee $employee) {
            $employee->full_name = $employee->buildFullName();
        });
    }

    public function buildFullName(): string
    {
        return trim(implode(' ', array_filter([
            trim((string) $this->first_name),
            trim((string) $this->last_name),
        ]))) ?: trim((string) $this->first_name);
    }

    protected static function searchableColumns(): array
    {
        return [
            'full_name',
            'code',
            'national_id',
            'phone_number',
            'email',
            'department.name',
            'designation.name',
        ];
    }

    protected array $allowedFilters = [
        'department_id',
        'designation_id',
        'employment_type',
        'employment_status',
        'gender',
        'is_active',
        'joining_date',
        'reports_to_id',
        'created_by',
    ];

    /**
     * What blocks a hard delete. Attendance and payroll arrive in later phases;
     * they are listed as they land.
     */
    protected function getRelationships(): array
    {
        return [
            'contracts' => __('hr.contracts'),
            'documents' => __('hr.documents'),
            'directReports' => __('hr.direct_reports'),
        ];
    }

    /**
     * The employee's financial identity. Managed by EmployeeObserver — never
     * assign this by hand outside EmployeeLedgerService.
     */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class, 'ledger_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reports_to_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'reports_to_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'province_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmployeeContract::class);
    }

    public function currentContract(): HasMany
    {
        return $this->contracts()->where('is_current', true);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->photo ? Storage::disk('public')->url($this->photo) : null,
        );
    }

    /**
     * The GL account slug this employee's salary is expensed to.
     */
    public function salaryExpenseSlug(): string
    {
        $type = $this->employment_type instanceof EmploymentType
            ? $this->employment_type
            : EmploymentType::tryFrom((string) $this->employment_type);

        return ($type ?? EmploymentType::Permanent)->salaryExpenseSlug();
    }

    public function scopeEmployed($query)
    {
        return $query->whereIn('employment_status', array_values(array_filter(
            EmploymentStatus::values(),
            fn (string $status) => EmploymentStatus::from($status)->isEmployed()
        )));
    }

    /**
     * The next free employee code for the acting branch.
     *
     * Trashed codes stay taken — the unique index includes deleted_at, but
     * reusing a code would still make a restored employee collide.
     */
    public static function nextCode(): string
    {
        $highest = (int) static::withTrashed()
            ->selectRaw("MAX(CASE WHEN code ~ '^EMP-[0-9]+$' THEN SUBSTRING(code FROM 5)::bigint END) as highest")
            ->value('highest');

        return 'EMP-'.str_pad((string) ($highest + 1), 4, '0', STR_PAD_LEFT);
    }
}

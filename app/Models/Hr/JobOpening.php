<?php

namespace App\Models\Hr;

use App\Enums\EmploymentType;
use App\Enums\JobOpeningStatus;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Administration\Designation;
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

/**
 * A vacancy.
 */
class JobOpening extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code', 'title', 'department_id', 'designation_id', 'employment_type',
        'vacancies', 'description', 'requirements', 'responsibilities',
        'min_salary', 'max_salary', 'currency_id', 'location',
        'posted_date', 'closing_date', 'status', 'hiring_manager_id', 'remark',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'department_id' => 'string',
            'designation_id' => 'string',
            'currency_id' => 'string',
            'hiring_manager_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'employment_type' => EmploymentType::class,
            'status' => JobOpeningStatus::class,
            'posted_date' => 'date',
            'closing_date' => 'date',
            'vacancies' => 'integer',
            'min_salary' => 'decimal:4',
            'max_salary' => 'decimal:4',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['code', 'title', 'location', 'description'];
    }

    protected array $allowedFilters = [
        'department_id', 'designation_id', 'employment_type', 'status',
        'hiring_manager_id', 'posted_date', 'closing_date', 'created_by',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hiring_manager_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function statusEnum(): JobOpeningStatus
    {
        return $this->status instanceof JobOpeningStatus
            ? $this->status
            : (JobOpeningStatus::tryFrom((string) $this->status) ?? JobOpeningStatus::Draft);
    }

    public function canTransitionTo(JobOpeningStatus $target): bool
    {
        return $this->statusEnum()->canTransitionTo($target);
    }

    /** How many of the advertised posts are still unfilled. */
    public function remainingVacancies(): int
    {
        $hired = $this->applications()
            ->where('status', \App\Enums\ApplicationStatus::Hired->value)
            ->count();

        return max(0, (int) $this->vacancies - $hired);
    }

    /**
     * Openings whose closing date has passed but which are still advertised.
     * The auto-close command reads this.
     */
    public function scopeOverdue($query, string $date)
    {
        return $query
            ->where('status', JobOpeningStatus::Published->value)
            ->whereNotNull('closing_date')
            ->whereDate('closing_date', '<', $date);
    }

    /**
     * Deleting an opening would orphan its candidates and every interview
     * booked against them, so it is blocked while any application exists.
     */
    protected function getRelationships(): array
    {
        return [
            'applications' => [
                'model' => 'job_applications',
                'message' => 'This job opening has applications',
            ],
        ];
    }
}

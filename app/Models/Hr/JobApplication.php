<?php

namespace App\Models\Hr;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Models\Administration\Currency;
use App\Models\Administration\Province;
use App\Traits\BranchSpecific;
use App\Traits\HasAttachments;
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
 * A candidate for a vacancy.
 *
 * Candidate details live here rather than in `employees`, and no ledger row is
 * created: most applicants never become employees. Putting them in `employees`
 * would corrupt every headcount and payroll query, and giving them ledgers
 * would fill the chart of accounts with people the company never paid.
 *
 * The CV is a plain attachment through HasAttachments — it needs no expiry
 * date or verification state, so it does not warrant its own table the way
 * employee_documents does.
 */
class JobApplication extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasAttachments, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'job_opening_id', 'application_number',
        'full_name', 'father_name', 'gender', 'date_of_birth', 'national_id',
        'phone_number', 'email', 'address', 'province_id',
        'current_employer', 'current_position', 'years_of_experience',
        'highest_education', 'expected_salary', 'currency_id', 'notice_period_days',
        'source', 'referred_by', 'status', 'score', 'rejection_reason',
        'applied_date', 'offered_date', 'offered_salary', 'hired_employee_id',
        'remark', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'job_opening_id' => 'string',
            'province_id' => 'string',
            'currency_id' => 'string',
            'hired_employee_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'gender' => Gender::class,
            'source' => ApplicationSource::class,
            'status' => ApplicationStatus::class,
            'date_of_birth' => 'date',
            'applied_date' => 'date',
            'offered_date' => 'date',
            'years_of_experience' => 'decimal:2',
            'expected_salary' => 'decimal:4',
            'offered_salary' => 'decimal:4',
            'score' => 'decimal:2',
            'notice_period_days' => 'integer',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'application_number', 'full_name', 'phone_number', 'email',
            'national_id', 'current_employer', 'current_position',
        ];
    }

    protected array $allowedFilters = [
        'job_opening_id', 'status', 'source', 'gender', 'province_id',
        'applied_date', 'created_by',
    ];

    public function opening(): BelongsTo
    {
        return $this->belongsTo(JobOpening::class, 'job_opening_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function hiredEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class)->orderBy('round');
    }

    public function statusEnum(): ApplicationStatus
    {
        return $this->status instanceof ApplicationStatus
            ? $this->status
            : (ApplicationStatus::tryFrom((string) $this->status) ?? ApplicationStatus::Applied);
    }

    public function canTransitionTo(ApplicationStatus $target): bool
    {
        return $this->statusEnum()->canTransitionTo($target);
    }

    /** The round number a newly scheduled interview should take. */
    public function nextInterviewRound(): int
    {
        return (int) $this->interviews()->max('round') + 1;
    }

    /**
     * The panel's average score across completed interviews.
     *
     * Presented alongside the individual recommendations, never instead of
     * them: an average hides the one interviewer with a strong objection, and
     * that is usually the opinion worth reading.
     */
    public function averageScore(): ?float
    {
        $scores = $this->interviews()
            ->whereNotNull('score')
            ->pluck('score')
            ->map(fn ($score) => (float) $score);

        return $scores->isEmpty() ? null : round($scores->avg(), 2);
    }

    protected function getRelationships(): array
    {
        return [
            'interviews' => [
                'model' => 'interviews',
                'message' => 'This application has interviews',
            ],
        ];
    }
}

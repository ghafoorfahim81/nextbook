<?php

namespace App\Models\Hr;

use App\Enums\InterviewRecommendation;
use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
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

/**
 * One round of interviewing for a candidate.
 */
class Interview extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'job_application_id', 'round', 'interview_type', 'scheduled_at',
        'duration_minutes', 'location', 'meeting_link', 'status',
        'score', 'recommendation', 'feedback', 'remark',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'job_application_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'interview_type' => InterviewType::class,
            'status' => InterviewStatus::class,
            'recommendation' => InterviewRecommendation::class,
            'scheduled_at' => 'datetime',
            'round' => 'integer',
            'duration_minutes' => 'integer',
            'score' => 'decimal:2',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['location', 'feedback', 'application.full_name', 'application.application_number'];
    }

    protected array $allowedFilters = [
        'job_application_id', 'interview_type', 'status', 'recommendation',
        'scheduled_at', 'created_by',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function panelists(): HasMany
    {
        return $this->hasMany(InterviewPanelist::class);
    }

    public function statusEnum(): InterviewStatus
    {
        return $this->status instanceof InterviewStatus
            ? $this->status
            : (InterviewStatus::tryFrom((string) $this->status) ?? InterviewStatus::Scheduled);
    }

    /**
     * The panel's verdict.
     *
     * A single strong objection blocks, whatever the others said — that is the
     * signal an averaged score would erase.
     */
    public function panelVerdict(): ?InterviewRecommendation
    {
        $submitted = $this->panelists()->whereNotNull('recommendation')->get();

        if ($submitted->isEmpty()) {
            return $this->recommendation instanceof InterviewRecommendation
                ? $this->recommendation
                : null;
        }

        $blocking = $submitted->first(
            fn (InterviewPanelist $panelist) => $panelist->recommendationEnum()?->isBlocking()
        );

        if ($blocking) {
            return InterviewRecommendation::StrongNoHire;
        }

        $positive = $submitted->filter(
            fn (InterviewPanelist $panelist) => $panelist->recommendationEnum()?->isPositive()
        )->count();

        return $positive > ($submitted->count() / 2)
            ? InterviewRecommendation::Hire
            : InterviewRecommendation::NoHire;
    }

    /** Interviews scheduled in a window, for the reminder command. */
    public function scopeScheduledBetween($query, string $from, string $to)
    {
        return $query
            ->where('status', InterviewStatus::Scheduled->value)
            ->whereBetween('scheduled_at', [$from, $to]);
    }
}

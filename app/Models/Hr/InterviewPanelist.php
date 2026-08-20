<?php

namespace App\Models\Hr;

use App\Enums\InterviewRecommendation;
use App\Models\User;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One person on an interview panel, and what they concluded.
 *
 * Feedback is per panelist rather than one field on the interview, because the
 * disagreement between two interviewers is the most useful thing in the record
 * and a single shared box loses it.
 */
class InterviewPanelist extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'interview_id', 'employee_id', 'user_id', 'role', 'is_lead',
        'score', 'recommendation', 'feedback', 'submitted_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'interview_id' => 'string',
            'employee_id' => 'string',
            'user_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'recommendation' => InterviewRecommendation::class,
            'is_lead' => 'boolean',
            'score' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recommendationEnum(): ?InterviewRecommendation
    {
        if ($this->recommendation instanceof InterviewRecommendation) {
            return $this->recommendation;
        }

        return $this->recommendation
            ? InterviewRecommendation::tryFrom((string) $this->recommendation)
            : null;
    }

    public function hasSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}

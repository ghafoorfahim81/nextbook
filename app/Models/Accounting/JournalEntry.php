<?php

namespace App\Models\Accounting;

use App\Enums\JournalEntrySource;
use App\Enums\JournalEntryStatus;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $table = 'journal_entries';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'number',
        'date',
        'status',
        'source',
        'financial_period_id',
        'reference_type',
        'reference_id',
        'posted_at',
        'posted_by',
        'reversal_of_id',
        'reversed_at',
        'post_blocked_reason',
        'description',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => JournalEntryStatus::class,
            'source' => JournalEntrySource::class,
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
            'financial_period_id' => 'string',
            'reference_id' => 'string',
            'posted_by' => 'string',
            'reversal_of_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }
}

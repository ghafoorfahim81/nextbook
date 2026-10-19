<?php

namespace App\Models\Accounting;

use App\Enums\FinancialPeriodStatus;
use App\Traits\CompanySpecific;
use App\Traits\HasCompany;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialPeriod extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, CompanySpecific, HasCompany, SoftDeletes;

    protected $table = 'financial_periods';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => FinancialPeriodStatus::class,
            'closed_at' => 'datetime',
            'company_id' => 'string',
            'closed_by' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'financial_period_id');
    }

    public function accountBalances(): HasMany
    {
        return $this->hasMany(AccountBalance::class, 'financial_period_id');
    }
}

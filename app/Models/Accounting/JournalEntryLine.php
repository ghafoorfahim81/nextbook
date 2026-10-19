<?php

namespace App\Models\Accounting;

use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Traits\CompanySpecific;
use App\Traits\HasCompanyFromParent;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntryLine extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, CompanySpecific, HasCompanyFromParent, SoftDeletes;

    protected $table = 'journal_entry_lines';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'journal_entry_id',
        'account_id',
        'description',
        'debit',
        'credit',
        'currency_id',
        'exchange_rate',
        'base_debit',
        'base_credit',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'journal_entry_id' => 'string',
            'account_id' => 'string',
            'currency_id' => 'string',
            'debit' => 'float',
            'credit' => 'float',
            'exchange_rate' => 'float',
            'base_debit' => 'float',
            'base_credit' => 'float',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    protected function companyParentRelation(): string
    {
        return 'journalEntry';
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}

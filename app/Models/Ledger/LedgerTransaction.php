<?php

namespace App\Models\Ledger;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;   
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\HasSorting;
use App\Models\Transaction\Transaction;
use App\Models\Ledger\Ledger;
use App\Traits\BranchSpecific;
use App\Traits\CompanySpecific;
use App\Traits\HasBranch;
use App\Traits\HasCompany;
class LedgerTransaction extends Model
{
    use HasFactory, HasUlids, HasSorting, SoftDeletes, CompanySpecific, BranchSpecific, HasCompany, HasBranch;
    protected $fillable = [
        'company_id',
        'transaction_id',
        'ledger_id',
        'deleted_by',
        'branch_id',
    ];

    protected $casts = [
        'transaction_id' => 'string',
        'ledger_id' => 'string',
        'deleted_by' => 'string',
        'branch_id' => 'string',
    ];  

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }
}

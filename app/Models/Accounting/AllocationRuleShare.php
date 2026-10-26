<?php

namespace App\Models\Accounting;

use App\Models\Administration\Branch;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One branch's percentage in one version of an allocation rule. */
class AllocationRuleShare extends Model
{
    use HasUlids, HasCompany;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'allocation_rule_id',
        'branch_id',
        'effective_from',
        'percentage',
    ];

    protected $casts = [
        'id' => 'string',
        'effective_from' => 'date',
        'percentage' => 'decimal:4',
    ];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AllocationRule::class, 'allocation_rule_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withoutGlobalScopes();
    }
}

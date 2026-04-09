<?php

namespace App\Models\Owner;

use App\Models\Account\Account;
use App\Models\Transaction\Transaction;
use App\Traits\HasBranch;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasDynamicFilters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\BranchSpecific; 
use Illuminate\Database\Eloquent\Relations\HasOne;
class Owner extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'father_name',
        'nic',
        'email',
        'address',
        'phone_number',
        'share_percentage',
        'profit_share_percentage',
        'is_active',
        'capital_account_id',
        'drawing_account_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
        'share_percentage' => 'float',
        'profit_share_percentage' => 'float', 
        'capital_account_id' => 'string',
        'drawing_account_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'deleted_by' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'father_name',
            'nic',
            'email',
            'phone_number',
        ];
    }

    protected array $allowedFilters = [
        'name',
        'nic',
        'created_by',
    ];

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'reference_id');
    }


    public function capitalAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'capital_account_id');
    }

    public function drawingAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'drawing_account_id');
    }
}


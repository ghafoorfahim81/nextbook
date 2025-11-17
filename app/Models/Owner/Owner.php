<?php

namespace App\Models\Owner;

use App\Models\Account\Account;
use App\Models\Transaction\Transaction;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class Owner extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'father_name',
        'nic',
        'email',
        'address',
        'phone_number',
        'ownership_percentage',
        'is_active',
        'capital_transaction_id',
        'account_transaction_id',
        'capital_account_id',
        'drawing_account_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
        'ownership_percentage' => 'float',
        'capital_transaction_id' => 'string',
        'account_transaction_id' => 'string',
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

    public function capitalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'capital_transaction_id');
    }

    public function accountTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'account_transaction_id');
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



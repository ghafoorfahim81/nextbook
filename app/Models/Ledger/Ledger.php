<?php

namespace App\Models\Ledger;

use App\Models\Administration\Branch;
use App\Models\LedgerOpening\LedgerOpening;
use App\Models\Transaction\Transaction;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ledger extends Model
{
    use HasFactory,HasUlids, HasSearch, HasSorting, HasUserAuditable;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'address',
        'contact_person',
        'phone_no',
        'branch_id',
        'email',
        'currency_id',
        'type',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'branch_id' => 'string',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Currency::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
    public function opening()
    {
        return $this->morphOne(LedgerOpening::class, 'ledgerable');
    }


}

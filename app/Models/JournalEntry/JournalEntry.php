<?php

namespace App\Models\JournalEntry;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use Laravel\Scout\Searchable;
use App\Traits\HasBranch;
use App\Traits\HasCache;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\BranchSpecific;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
class JournalEntry extends Model
{
    use HasFactory, HasUlids, HasCache, HasSearch, HasSorting, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $table = 'journal_entries';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'number',
        'date',
        'remark',
        'status',
        'branch_id'
    ];
    protected $casts = [
        'id' => 'string',
        'date' => 'date',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'number',
            'description',
        ];
    }

    protected function getRelationships(): array
    {
        return [
            'transaction' => [
                'model' => 'transactions',
            ],
        ];
    }
     /* -----------------------------------------------------------------
     |  Relationships
     | -----------------------------------------------------------------
     */

    /**
     * Journal Entry creates exactly ONE transaction
     */
    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'reference_id');
    }

    /**
     * Shortcut to transaction lines
     */
}

<?php

namespace App\Models\JournalEntry;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\HasCache;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasDynamicFilters;
use App\Traits\HasUserAuditable;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use Illuminate\Database\Eloquent\SoftDeletes;
class JournalType extends Model
{
    use HasFactory, HasUlids, HasCache, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $table = 'journal_types';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'id' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'description',
        ];
    }

    protected array $allowedFilters = [
        'name',
        'description',
        'created_by',
    ];

    protected function getRelationships(): array
    {
        return [
            'journal_entries' => [
                'model' => 'journal_entries',
            ],
        ];
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'deleted_by' => 'integer',
        ];
    }

}

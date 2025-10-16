<?php

namespace App\Models\Administration;

use App\Traits\HasBranch;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class Store extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasSearch, HasSorting, HasBranch, SoftDeletes;

    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'address',
        'is_main',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'is_main' => 'boolean',
        'branch_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'parent_id' => 'string',
    ];


    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

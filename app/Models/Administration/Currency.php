<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use Symfony\Component\Uid\Ulid;

class Currency extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasSearch, HasSorting;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $keyType = 'string';
    public $incrementing = false; // Disable auto-incrementing
    
    protected $fillable = [
        'name',
        'code',
        'symbol',
        'format',
        'exchange_rate',
        'is_active',
        'flag',
        'branch_id',
        'tenant_id',
        'created_by',
        'updated_by',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new Ulid(); // Generate ULID
        });
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'exchange_rate' => 'decimal',
        'is_active' => 'boolean',
        'branch_id' => 'integer',
        'tenant_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'code',
            'symbol',
            'format',
            'flag',
            'branch.name',
            'tenant.name'
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    
}

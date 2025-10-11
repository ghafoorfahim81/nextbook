<?php

namespace App\Models\Administration;

use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class UnitMeasure extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasSearch, HasSorting, HasBranch, HasDependencyCheck;

    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new Ulid(); // Generate ULID
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'unit',
        'symbol',
        'description',
        'branch_id',
        'quantity_id',
        'value',
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
        'branch_id' => 'string',
        'quantity_id' => 'string',
        'value' => 'double',
        'created_by' => 'string',
        'updated_by' => 'string',
        'parent_id' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
        'name',
        'unit',
        'symbol',
        'quantity.quantity',
        'quantity.unit',
        'quantity.symbol',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function quantity(): BelongsTo
    {
        return $this->belongsTo(Quantity::class);
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'items' => [
                'model' => 'items',
                'message' => 'This unit measure is used in items'
            ],
            'stocks' => [
                'model' => 'stock records',
                'message' => 'This unit measure is used in stock records'
            ]
        ];
    }

    /**
     * Relationship to items that use this unit measure
     */
    public function items()
    {
        return $this->hasMany(\App\Models\Inventory\Item::class, 'unit_measure_id');
    }

    /**
     * Relationship to stocks that use this unit measure
     */
    public function stocks()
    {
        return $this->hasMany(\App\Models\Inventory\Stock::class, 'unit_measure_id');
    }

}

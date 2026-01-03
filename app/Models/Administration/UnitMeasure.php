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
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;
use App\Traits\HasCache;
use App\Traits\BranchSpecific;
class UnitMeasure extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasCache, HasSearch, HasSorting, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

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
        'is_main',
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
        'is_main' => 'boolean',
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

    public static function defaultUnitMeasures(): array
    {
        return [
            // count
            [
                'name'        => 'pcs',
                'unit'        => 1,
                'symbol'      => "ea",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Count')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'Pair',
                'unit'        => 2,
                'symbol'      => "pr",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Count')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'Dozen',
                'unit'        => 12,
                'symbol'      => "dz",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Count')->first()->id,
                'is_main'   => true,
            ],
            // length
            [
                'name'        => 'Centimetre',
                'unit'        => 1,
                'symbol'      => "cm",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Length')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'Meter',
                'unit'        => 100,
                'symbol'      => "m",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Length')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'Inch',
                'unit'        => 2.5,
                'symbol'      => "in",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Length')->first()->id,
                'is_main'   => true,
            ],
            // area
            [
                'name'        => 'SquareCentimetre',
                'unit'        => 1,
                'symbol'      => "cm2",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Area')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'SquareDecimeter',
                'unit'        => 0.01,
                'symbol'      => "dm2",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Area')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'SquareMeter',
                'unit'        => 0.0001,
                'symbol'      => "m2",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Area')->first()->id,
                'is_main'   => true,
            ],
            // weight
            [
                'name'        => 'Gram',
                'unit'        => 1,
                'symbol'      => "g",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Weight')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'Kilogram',
                'unit'        => 1000,
                'symbol'      => "kg",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Weight')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'Ton',
                'unit'        => 1000000,
                'symbol'      => "ton",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Weight')->first()->id,
                'is_main'   => true,
            ],
            // volume
            [
                'name'        => 'Millilitre',
                'unit'        => 1,
                'symbol'      => "ml",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Volume')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'Litre',
                'unit'        => 1000,
                'symbol'      => "L",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Volume')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'Gallon',
                'unit'        => 3785.41, // US Gallon to ml
                'symbol'      => "gal",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Volume')->first()->id,
                'is_main'   => true,
            ],
            [
                'name'        => 'Barrel',
                'unit'        => 158987.294928, // نفت خام به ml
                'symbol'      => "bbl",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('quantity', 'Volume')->first()->id,
                'is_main'   => true,
            ],
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

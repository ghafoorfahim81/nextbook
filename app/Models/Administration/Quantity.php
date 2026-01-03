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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;
use App\Traits\BranchSpecific;
class Quantity extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasSearch, HasSorting, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new Ulid(); // Generate ULID
        });
    }
    protected $fillable = [
        'quantity',
        'unit',
        'symbol',
        'slug',
        'branch_id',
        'is_main',
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
        'slug' => 'string',
        'is_main' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
        'parent_id' => 'string',
    ];

    public static function defaultQuantity(): array
    {
        return [
            [
            'quantity' => 'Count',
            'unit'       => "Pcs",
            'slug'       => "count",
            'symbol'     => "ea",
            'is_main'  => true,
            ],
            [
                'quantity' => 'Length',
                'unit'       => "Centimetre",
                'slug'       => "length",
                'symbol'     => "cm",
                'is_main'  => true,
            ],
            [
                'quantity' => 'Area',
                'unit'       => "SquareCentimetre",
                'slug'       => "area",
                'symbol'     => "cm2",
                'is_main'  => true,
            ],
            [
                'quantity' => 'Weight',
                'unit'       => "Gram",
                'slug'       => "weight",
                'symbol'     => "g",
                'is_main'  => true,
            ],

            [
                'quantity' => 'Volume',
                'unit'       => "Millilitre",
                'slug'       => "volume",
                'symbol'     => "ml",
                'is_main'  => true,
            ],

            [
                'quantity' => 'Temperature',
                'slug'       => "temperature",
                'unit'       => "Celsius",
                'symbol'     => "°C",
                'is_main'  => true,
            ],
        ];
    }
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function measures(): HasMany
    {
        return $this->hasMany(UnitMeasure::class, 'quantity_id');
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'measures' => [
                'model' => 'unit measures',
                'message' => 'This quantity is used in unit measures'
            ]
        ];
    }
}

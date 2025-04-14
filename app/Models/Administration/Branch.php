<?php

namespace App\Models\Administration;

use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class Branch extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasSearch, HasSorting;

    protected $table = 'branches';
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
        'is_main',
        'parent_id',
        'location',
        'sub_domain',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'location',
            'sub_domain',
            'remark',
            'parent.name'
        ];
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_main' => 'boolean',
        'parent_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

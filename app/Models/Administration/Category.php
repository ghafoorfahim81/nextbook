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

class Category extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasSearch, HasSorting;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $table = 'categories';
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
        'parent_id',
        'remark',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'parent_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

}

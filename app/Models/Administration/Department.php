<?php

namespace App\Models\Administration;

use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class Department extends Model
{
    use HasFactory, HasUserAuditable, HasUlids;

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
        'id',
        'name',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];


}

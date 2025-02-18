<?php

namespace App\Models\Administration;

use App\Traits\HasUserAuditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    use HasFactory, HasUserAuditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
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

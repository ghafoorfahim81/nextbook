<?php

namespace App\Models\Administration;

use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasCache;
class Designation extends Model
{
    use HasFactory, HasUuids, HasUserAuditable, HasCache, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'remark',
        'created_by',
        'updated_by',
    ];


}

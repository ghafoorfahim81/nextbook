<?php

namespace App\Models;

use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, HasBranch, BranchSpecific, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'attachable_type',
        'attachable_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id' => 'string',
        'size' => 'integer',
        'created_by' => 'string',
        'updated_by' => 'string',
        'deleted_by' => 'string',
    ];

    protected $appends = ['url'];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}

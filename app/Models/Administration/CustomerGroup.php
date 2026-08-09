<?php

namespace App\Models\Administration;

use App\Traits\HasSearch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerGroup extends Model
{
    use HasFactory, HasUlids, HasSearch, HasUserAuditable, SoftDeletes;

    protected $fillable = [
        'name_en',
        'name_fa',
        'description',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $appends = ['localized_name'];

    protected static function searchableColumns(): array
    {
        return ['name_en', 'name_fa', 'description'];
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'fa' ? $this->name_fa : $this->name_en;
    }
}

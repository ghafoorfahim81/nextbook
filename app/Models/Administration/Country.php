<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['code', 'name_en', 'name_fa'];

    protected $appends = ['localized_name'];

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'fa' ? $this->name_fa : $this->name_en;
    }
}

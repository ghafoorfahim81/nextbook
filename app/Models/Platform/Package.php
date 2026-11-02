<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A plan the platform sells (phase 10): the modules, users and branches it
 * includes, and its price. Global — every company draws from the same list.
 */
class Package extends Model
{
    use HasUlids, SoftDeletes;

    protected $table = 'packages';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code', 'name', 'description', 'modules', 'max_users', 'max_branches',
        'price', 'currency', 'period_months', 'is_active', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'max_users' => 'integer',
            'max_branches' => 'integer',
            'price' => 'decimal:4',
            'period_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Whether the package includes a module (config/modules.php key). */
    public function includesModule(string $module): bool
    {
        return in_array($module, (array) $this->modules, true);
    }
}

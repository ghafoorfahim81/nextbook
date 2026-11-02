<?php

namespace App\Models\Platform;

use App\Models\Administration\Company;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A company's subscription (phase 10): its package and the dates it runs.
 *
 * Its state is worked out, never stored, except for the block, which is a
 * decision someone took:
 *   blocked  - blocked_at is set;
 *   expired  - ends_on is behind us;
 *   expiring - ends within EXPIRING_DAYS, still working but worth a warning;
 *   active   - otherwise.
 * Blocked and expired both make the company read-only (EnforceSubscription);
 * nothing is ever locked or deleted.
 */
class Subscription extends Model
{
    use HasUlids;

    public const EXPIRING_DAYS = 14;

    protected $table = 'subscriptions';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id', 'package_id', 'starts_on', 'ends_on',
        'blocked_at', 'block_reason', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'blocked_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class)->withTrashed();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function isExpired(?Carbon $on = null): bool
    {
        return $this->ends_on->lt(($on ?? now())->startOfDay());
    }

    /** Days left, counting today; negative once expired. */
    public function daysLeft(?Carbon $on = null): int
    {
        return (int) ($on ?? now())->startOfDay()->diffInDays($this->ends_on, false);
    }

    /** blocked, expired, expiring or active. */
    public function state(?Carbon $on = null): string
    {
        return match (true) {
            $this->isBlocked() => 'blocked',
            $this->isExpired($on) => 'expired',
            $this->daysLeft($on) <= self::EXPIRING_DAYS => 'expiring',
            default => 'active',
        };
    }

    /** Whether the company may write: not blocked, not expired. */
    public function allowsWrites(?Carbon $on = null): bool
    {
        return ! $this->isBlocked() && ! $this->isExpired($on);
    }
}

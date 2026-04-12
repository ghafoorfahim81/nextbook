<?php

namespace App\Models;

use App\Models\Administration\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'activity_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'event_type',
        'module',
        'reference_type',
        'reference_id',
        'user_id',
        'branch_id',
        'ip_address',
        'user_agent',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'branch_id' => 'string',
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function scopeForModule(Builder $query, ?string $module): Builder
    {
        return $query->when($module, fn (Builder $builder) => $builder->where('module', $module));
    }

    public function scopeForEventType(Builder $query, ?string $eventType): Builder
    {
        return $query->when($eventType, fn (Builder $builder) => $builder->where('event_type', $eventType));
    }

    public function scopeForUser(Builder $query, ?string $userId): Builder
    {
        return $query->when($userId, fn (Builder $builder) => $builder->where('user_id', $userId));
    }

    public function scopeForBranch(Builder $query, ?string $branchId): Builder
    {
        return $query->when($branchId, fn (Builder $builder) => $builder->where('branch_id', $branchId));
    }

    public function scopeForReference(Builder $query, ?string $referenceType = null, ?string $referenceId = null): Builder
    {
        return $query
            ->when($referenceType, fn (Builder $builder) => $builder->where('reference_type', $referenceType))
            ->when($referenceId, fn (Builder $builder) => $builder->where('reference_id', $referenceId));
    }

    public function scopeBetweenDates(Builder $query, ?string $from = null, ?string $to = null): Builder
    {
        return $query
            ->when($from, fn (Builder $builder) => $builder->where('created_at', '>=', $from))
            ->when($to, fn (Builder $builder) => $builder->where('created_at', '<=', $to));
    }
}

<?php

namespace App\Models;

use App\Enums\NotificationCategory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'dedupe_key',
        'title',
        'message',
        'is_read',
        'archived_at',
        'favorited_at',
        'data',
    ];

    protected $appends = ['category'];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'is_read' => 'boolean',
            'archived_at' => 'datetime',
            'favorited_at' => 'datetime',
            'data' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getCategoryAttribute(): string
    {
        return NotificationCategory::forType($this->type)->value;
    }

    public function scopeActive($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeFavorited($query)
    {
        return $query->whereNotNull('favorited_at');
    }
}

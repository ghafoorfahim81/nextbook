<?php

namespace App\Models\POS;

use App\Enums\PosSessionStatus;
use App\Models\User;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosSession extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $table = 'pos_sessions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'opened_at',
        'closed_at',
        'status',
        'cashier_id',
        'opening_cash',
        'expected_cash',
        'closing_cash',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => PosSessionStatus::class,
            'cashier_id' => 'string',
            'opening_cash' => 'float',
            'expected_cash' => 'float',
            'closing_cash' => 'float',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}

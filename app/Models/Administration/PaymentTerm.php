<?php

namespace App\Models\Administration;

use App\Traits\HasSearch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTerm extends Model
{
    use HasFactory, HasUlids, HasSearch, HasUserAuditable, SoftDeletes;

    protected $fillable = ['name', 'days', 'type', 'branch_id', 'created_by', 'updated_by'];

    protected $casts = ['days' => 'integer'];

    protected static function searchableColumns(): array
    {
        return ['name', 'type'];
    }
}

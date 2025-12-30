<?php

namespace App\Models\Expense;

use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BranchSpecific;
class ExpenseCategory extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting,BranchSpecific, HasBranch, HasUserAuditable, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'remarks',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return ['name', 'remarks'];
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    protected function getRelationships(): array
    {
        return [
            'expenses' => [
                'model' => 'expenses',
                'message' => 'This category is used in expenses'
            ]
        ];
    }
}


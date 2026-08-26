<?php

namespace App\Models\Administration;

use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasCache;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandedCostCategory extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasCache, HasDependencyCheck, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'remark',
        ];
    }

    protected function getRelationships(): array
    {
        return [];
    }

    public static function defaultCategories(): array
    {
        return [
            ['name' => 'Freight & Transport', 'remark' => null],
            ['name' => 'Customs & Government', 'remark' => null],
            ['name' => 'Handling & Port', 'remark' => null],
            ['name' => 'Storage & Warehousing', 'remark' => null],
            ['name' => 'Agent & Intermediary', 'remark' => null],
            ['name' => 'Insurance & Risk', 'remark' => null],
            ['name' => 'Financial & Currency', 'remark' => null],
            ['name' => 'Compliance & Certification', 'remark' => null],
            ['name' => 'Other', 'remark' => null],
        ];
    }
}

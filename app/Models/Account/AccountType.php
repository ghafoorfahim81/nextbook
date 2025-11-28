<?php

namespace App\Models\Account;

use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasBranch;
class AccountType extends Model
{
    use HasFactory, HasSearch, HasSorting, HasBranch, HasUserAuditable, HasDependencyCheck, SoftDeletes;

    protected $table = 'account_types';
    protected $keyType = 'string';
    public $incrementing = false;
    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new \Symfony\Component\Uid\Ulid();
        });
    }
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'remark',
        'slug',
        'is_main',
        'created_by',
        'updated_by',
    ];


    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'slug' => 'string',
        'is_main' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'remark',
        ];
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'accounts' => [
                'model' => 'accounts',
                'message' => 'This account type is used in accounts'
            ]
        ];
    }

    /**
     * Relationship to accounts that use this account type
     */
    public function accounts()
    {
        return $this->hasMany(\App\Models\Account\Account::class, 'account_type_id');
    }
}

<?php

namespace App\Models;

use App\Models\Administration\Company;
// use Illuminate\Contracts\Auth\MustVerifyEmail; 
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable; 
use Laravel\Fortify\TwoFactorAuthenticatable; 
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class User extends Authenticatable
{ 
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, HasDependencyCheck, SoftDeletes, HasRoles;

    use TwoFactorAuthenticatable;

   
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'branch_id',
        'company_id',
    ];

    protected $keyType = 'string';

    // Disable auto-incrementing for the primary key
    public $incrementing = false;

    /**
     * Get the company that owns the user.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    // protected $appends = [
    //     'profile_photo_url',
    // ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}

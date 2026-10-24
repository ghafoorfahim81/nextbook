<?php

namespace App\Models;

use App\Models\Administration\Branch;
use App\Models\Administration\Company;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasDependencyCheck;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Enums\UserStatus;
use App\Traits\BranchSpecific;
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasUlids, HasSorting, HasUserAuditable, HasDependencyCheck, SoftDeletes, HasRoles;

    use TwoFactorAuthenticatable, HasProfilePhoto;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'locale',
        'password',
        'status',
        'branch_id',
        'company_id',
        'preferences',
        'profile_photo_path',
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

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Companies granted on top of the home company. UserAccessService is what
     * decides access; this is for listing.
     */
    public function companyAccess()
    {
        return $this->hasMany(\App\Models\Administration\UserCompanyAccess::class);
    }

    public function branchAccess()
    {
        return $this->hasMany(\App\Models\Administration\UserBranchAccess::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles->contains('slug', 'super-admin');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
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
    protected $appends = [
        'profile_photo_url',
    ];

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
            'preferences' => 'array',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Get a preference value with dot notation support.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        $preferences = $this->preferences ?? [];
        return data_get($preferences, $key, data_get(\App\Support\Settings::defaults(), $key, $default));
    }

    /**
     * Set a preference value with dot notation support.
     */
    public function setPreference(string $key, mixed $value): self
    {
        $preferences = $this->preferences ?? \App\Support\Settings::defaults();
        data_set($preferences, $key, $value);
        $this->preferences = $preferences;
        return $this;
    }

    /**
     * Get all preferences merged with defaults.
     */
    public function getAllPreferences(): array
    {
        $preferences = array_replace_recursive(\App\Support\Settings::defaults(), $this->preferences ?? []);

        // array_replace_recursive merges list-style preferences by numeric
        // index, so a stored list that is shorter than the default keeps the
        // default's leftover tail entries (e.g. a user who hides all but two
        // sidebar menus still gets ~20 back). Restore the sidebar menu list
        // from the raw stored value so the user's exact selection is honoured,
        // and fold away legacy keys (receipt/payment were merged into the
        // cash_transactions group; transfer moved under the Account menu;
        // the standalone trash menu moved into the system group).
        $storedMenus = data_get($this->preferences, 'appearance.sidebar_menus');

        if (is_array($storedMenus)) {
            $normalizedMenus = [];

            foreach ($storedMenus as $menu) {
                $menu = match ($menu) {
                    'receipt', 'payment' => 'cash_transactions',
                    'trash' => 'system',
                    default => $menu,
                };

                if ($menu === 'transfer' || $menu === 'preferences' || ! is_string($menu) || $menu === '') {
                    continue;
                }

                $normalizedMenus[$menu] = true;
            }

            data_set($preferences, 'appearance.sidebar_menus', array_keys($normalizedMenus));
        }

        foreach (['sale', 'sale_order', 'sale_return', 'sale_quotation', 'purchase', 'purchase_order', 'purchase_return', 'purchase_quotation'] as $module) {
            $fields = &$preferences[$module]['general_fields'];
            $legacyFields = data_get($this->preferences, "{$module}.general_fields", []);

            if (is_array($legacyFields) && array_key_exists('store', $legacyFields) && ! array_key_exists('warehouse', $legacyFields)) {
                $fields['warehouse'] = $legacyFields['store'];
            }

            unset($fields['store']);
            unset($fields);
        }

        $itemFields = &$preferences['item_management']['visible_fields'];
        if (is_array($itemFields) && array_key_exists('file_upload', $itemFields) && ! array_key_exists('photo', $itemFields)) {
            $itemFields['photo'] = $itemFields['file_upload'];
        }
        unset($itemFields);

        return $preferences;
    }

    /**
     * Reset preferences to defaults.
     */
    public function resetPreferences(?string $category = null): self
    {
        if ($category) {
            $preferences = $this->preferences ?? [];
            $preferences[$category] = \App\Support\Settings::defaults()[$category] ?? [];
            $this->preferences = $preferences;
        } else {
            $this->preferences = \App\Support\Settings::defaults();
        }
        return $this;
    }
}

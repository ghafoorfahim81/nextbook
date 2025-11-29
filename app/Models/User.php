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
        'preferences',
    ];

    protected $keyType = 'string';

    // Disable auto-incrementing for the primary key
    public $incrementing = false;

    /**
     * Default preferences structure.
     */
    public const DEFAULT_PREFERENCES = [
        'appearance' => [
            'font_size' => 14, 
            'decimal_places' => 2,
            'sidebar_menus' => ['dashboard', 'sales', 'purchases', 'ledger', 'owners', 'account', 'purchase', 'sale', 'receipt', 'payment', 'transfer', 'user_management', 'preferences'],
        ],
        'item_management' => [
            'visible_fields' => [
                'code' => true,
                'generic_name' => false,
                'packing' => true,
                'colors' => false,
                'size' => false,
                'brand' => true,
                'minimum_stock' => true,
                'maximum_stock' => true,
                'file_upload' => true,
                'rate_a' => true,
                'rate_b' => false,
                'rate_c' => false,
                'barcode' => true,
                'rack_no' => false,
                'fast_search' => true,
            ],
            'spec_text' => '',
        ],
        'sales' => [
            'general_fields' => ['number' => true, 'date' => true, 'currency' => true, 'type' => true, 'store' => true],
            'item_columns' => ['packing' => false, 'batch' => false, 'expiry' => false, 'on_hand' => true, 'measure' => true, 'discount' => true, 'tax' => false, 'free' => false],
            'invoice_prefix' => 'INV-',
            'start_number' => 1,
            'terms' => '',
            'due_days' => 30,
            'auto_reminders' => false,
            'reminder_days' => 7,
            'late_fee_percentage' => 0,
            'tax_percentage' => 0,
            'auto_calculate_tax' => true,
            'show_ledger_transactions' => true,
            'show_item_transactions' => true,
        ],
        'sales_order' => [
            'general_fields' => ['number' => true, 'date' => true, 'currency' => true, 'type' => false, 'store' => true],
            'item_columns' => ['packing' => false, 'batch' => false, 'expiry' => false, 'on_hand' => true, 'measure' => true, 'discount' => true, 'tax' => false, 'free' => false],
            'invoice_prefix' => 'SO-',
            'start_number' => 1,
            'terms' => '',
            'due_days' => 30,
        ],
        'sales_return' => [
            'general_fields' => ['number' => true, 'date' => true, 'currency' => true, 'type' => false, 'store' => true],
            'item_columns' => ['packing' => false, 'batch' => false, 'expiry' => false, 'on_hand' => true, 'measure' => true, 'discount' => true, 'tax' => false, 'free' => false],
            'invoice_prefix' => 'SR-',
            'start_number' => 1,
        ],
        'sales_quotation' => [
            'general_fields' => ['number' => true, 'date' => true, 'currency' => true, 'type' => false, 'store' => true],
            'item_columns' => ['packing' => false, 'batch' => false, 'expiry' => false, 'on_hand' => true, 'measure' => true, 'discount' => true, 'tax' => false, 'free' => false],
            'invoice_prefix' => 'SQ-',
            'start_number' => 1,
        ],
        'purchases' => [
            'general_fields' => ['number' => true, 'date' => true, 'currency' => true, 'type' => false, 'store' => true],
            'item_columns' => ['packing' => false, 'batch' => false, 'expiry' => false, 'on_hand' => true, 'measure' => true, 'discount' => true, 'tax' => false, 'free' => false],
            'invoice_prefix' => 'PUR-',
            'start_number' => 1,
            'terms' => '',
            'due_days' => 30,
            'auto_reminders' => false,
            'reminder_days' => 7,
            'late_fee_percentage' => 0,
            'show_ledger_transactions' => true,
            'show_item_transactions' => true,
        ],
        'purchase_order' => [
            'general_fields' => ['number' => true, 'date' => true, 'currency' => true, 'type' => false, 'store' => true],
            'item_columns' => ['packing' => false, 'batch' => false, 'expiry' => false, 'on_hand' => true, 'measure' => true, 'discount' => true, 'tax' => false, 'free' => false],
            'invoice_prefix' => 'PO-',
            'start_number' => 1,
        ],
        'purchase_return' => [
            'general_fields' => ['number' => true, 'date' => true, 'currency' => true, 'type' => false, 'store' => true],
            'item_columns' => ['packing' => false, 'batch' => false, 'expiry' => false, 'on_hand' => true, 'measure' => true, 'discount' => true, 'tax' => false, 'free' => false],
            'invoice_prefix' => 'PR-',
            'start_number' => 1,
        ],
        'purchase_quotation' => [
            'general_fields' => ['number' => true, 'date' => true, 'currency' => true, 'type' => false, 'store' => true],
            'item_columns' => ['packing' => false, 'batch' => false, 'expiry' => false, 'on_hand' => true, 'measure' => true, 'discount' => true, 'tax' => false, 'free' => false],
            'invoice_prefix' => 'PQ-',
            'start_number' => 1,
        ],
        'receipt_payment' => [
            'visible_fields' => ['number' => true, 'currency' => true, 'cheque_number' => false, 'debit_account' => true, 'ledger_old_balance' => true],
            'default_cash_account' => null,
            'auto_sequence' => true,
            'require_approval' => false,
            'lock_after_days' => 0,
        ],
        'tax_currency' => [
            'tax_plus' => true,
            'tax_minus' => false,
            'multi_currency_opening' => false,
        ],
        'notifications' => [
            'email_notifications' => true,
            'low_balance_alert' => true,
            'overdue_invoice_alert' => true,
            'new_transaction_alert' => false,
            'daily_summary_report' => false,
            'weekly_financial_summary' => false,
        ],
        'security' => [
            'session_timeout' => 60,
            'password_min_length' => 8,
            'password_special_chars' => true,
            'two_factor_auth' => false,
            'login_attempts_limit' => 5,
            'lock_reports' => false,
            'lock_password' => '',
        ],
        'backup' => [
            'auto_backup' => 'none',
            'backup_retention_days' => 30,
            'cloud_backup' => false,
            'cloud_provider' => null,
            'export_pdf' => true,
            'export_excel' => true,
            'export_csv' => false,
        ],
        'localization' => [
            'language' => 'en',
            'date_format' => 'Y-m-d',
            'time_format' => '24h',
            'timezone' => 'UTC',
            'number_format' => '1,000.00',
            'first_day_of_week' => 'saturday',
        ],
        'display' => [
            'theme' => 'system',
            'dashboard_charts' => true,
            'records_per_page' => 10,
            'show_currency_symbol' => true,
            'compact_view' => false,
            'sidebar_collapsed' => false,
        ],
    ];

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
            'preferences' => 'array',
        ];
    }

    /**
     * Get a preference value with dot notation support.
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        $preferences = $this->preferences ?? [];
        return data_get($preferences, $key, data_get(self::DEFAULT_PREFERENCES, $key, $default));
    }

    /**
     * Set a preference value with dot notation support.
     */
    public function setPreference(string $key, mixed $value): self
    {
        $preferences = $this->preferences ?? self::DEFAULT_PREFERENCES;
        data_set($preferences, $key, $value);
        $this->preferences = $preferences;
        return $this;
    }

    /**
     * Get all preferences merged with defaults.
     */
    public function getAllPreferences(): array
    {
        return array_replace_recursive(self::DEFAULT_PREFERENCES, $this->preferences ?? []);
    }

    /**
     * Reset preferences to defaults.
     */
    public function resetPreferences(?string $category = null): self
    {
        if ($category) {
            $preferences = $this->preferences ?? [];
            $preferences[$category] = self::DEFAULT_PREFERENCES[$category] ?? [];
            $this->preferences = $preferences;
        } else {
            $this->preferences = self::DEFAULT_PREFERENCES;
        }
        return $this;
    }
}

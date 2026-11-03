<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * What the control center last told this company it may do (see the migration).
 * Written only by Control\LicenseClient and the license:connect command.
 */
class CompanyLicense extends Model
{
    use HasUlids;

    protected $table = 'company_licenses';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected $hidden = ['api_token'];

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'payload' => 'array',
            'invoices' => 'array',
            'invoices_fetched_at' => 'datetime',
            'license_version' => 'integer',
            'grace_days' => 'integer',
            'fetched_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }
}

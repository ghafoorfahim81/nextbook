<?php

namespace App\Models\Administration;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company a user may act in, on top of their home company.
 *
 * Read by SetActiveCompany before any company is on the connection, so the
 * table is outside row level security and the model carries no company scope.
 * Who may write it is decided by UserAccessService.
 */
class UserCompanyAccess extends Model
{
    use HasUlids;

    protected $table = 'user_company_access';

    protected $fillable = ['user_id', 'company_id', 'created_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

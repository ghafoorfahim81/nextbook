<?php

namespace App\Models\Administration;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A branch a user may act in, within a company they have access to.
 *
 * The foreign keys hold both rules: the branch belongs to company_id, and the
 * user has been given company_id. See UserCompanyAccess for why there is no
 * company scope here.
 */
class UserBranchAccess extends Model
{
    use HasUlids;

    protected $table = 'user_branch_access';

    protected $fillable = ['user_id', 'company_id', 'branch_id', 'created_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}

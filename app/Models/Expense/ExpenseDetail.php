<?php

namespace App\Models\Expense;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasUserAuditable;
use App\Traits\CompanySpecific;
use App\Traits\HasCompanyFromParent;
class ExpenseDetail extends Model
{
    use HasFactory, HasUlids, SoftDeletes, HasUserAuditable, CompanySpecific, HasCompanyFromParent;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'company_id',
        'expense_id',
        'amount',
        'title',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'expense_id' => 'string',
        'amount' => 'float',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** No branch of its own: the expense holds it, and the company comes from there too. */
    protected function companyParentRelation(): string
    {
        return 'expense';
    }
}

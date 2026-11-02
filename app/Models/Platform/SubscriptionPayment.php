<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A payment recorded by hand against a subscription invoice (phase 10). */
class SubscriptionPayment extends Model
{
    use HasUlids;

    public const METHODS = ['cash', 'hawala', 'bank', 'other'];

    protected $table = 'subscription_payments';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'subscription_invoice_id', 'company_id', 'amount', 'paid_on', 'method', 'reference', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class, 'subscription_invoice_id');
    }
}

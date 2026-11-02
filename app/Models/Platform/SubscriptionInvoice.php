<?php

namespace App\Models\Platform;

use App\Models\Administration\Company;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What the platform bills a company for a subscription period (phase 10).
 * Recorded by hand; whether it is paid follows from its payments.
 */
class SubscriptionInvoice extends Model
{
    use HasUlids;

    protected $table = 'subscription_invoices';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'number', 'company_id', 'subscription_id', 'issued_on', 'due_on',
        'period_from', 'period_to', 'amount', 'currency', 'description', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'period_from' => 'date',
            'period_to' => 'date',
            'amount' => 'decimal:4',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class)->withoutGlobalScopes();
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class)->orderBy('paid_on')->orderBy('created_at');
    }

    public function paidAmount(): string
    {
        return bcadd((string) $this->payments->sum('amount'), '0', 4);
    }

    public function balance(): string
    {
        return $this->status === 'void' ? '0.0000' : bcsub((string) $this->amount, $this->paidAmount(), 4);
    }

    /** void, paid, partial or unpaid. */
    public function paymentState(): string
    {
        return match (true) {
            $this->status === 'void' => 'void',
            bccomp($this->balance(), '0', 4) <= 0 => 'paid',
            bccomp($this->paidAmount(), '0', 4) > 0 => 'partial',
            default => 'unpaid',
        };
    }
}

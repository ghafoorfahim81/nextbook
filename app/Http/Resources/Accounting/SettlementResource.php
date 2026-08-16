<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One application of a voucher against one claim, as the UI needs it.
 *
 * Both rates are exposed, not just the difference. "Booked at 60, settled at
 * 55" is the explanation a user needs when they ask where a 1,000 afghani
 * expense came from; a bare forex figure is not.
 */
class SettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(\App\Services\DateConversionService::class);

        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'target_line_id' => $this->target_line_id,
            'settling_line_id' => $this->settling_line_id,
            'ledger_id' => $this->ledger_id,
            'currency_id' => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'amount_applied' => (float) $this->amount_applied,
            'target_rate' => (float) $this->target_rate,
            'settlement_rate' => (float) $this->settlement_rate,
            'base_relieved' => (float) $this->base_relieved,
            'forex_amount' => (float) $this->forex_amount,
            // Positive is a gain on both the receivable and the payable side.
            'forex_kind' => $this->forex_amount < 0 ? 'loss' : ($this->forex_amount > 0 ? 'gain' : 'none'),
            'is_cross_currency' => (bool) $this->is_cross_currency,
            'date' => $this->created_at ? $dates->toDisplay($this->created_at) : null,
        ];
    }
}

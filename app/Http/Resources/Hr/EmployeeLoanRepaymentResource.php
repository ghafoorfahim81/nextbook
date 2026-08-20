<?php

namespace App\Http\Resources\Hr;

use App\Enums\LoanRepaymentSource;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLoanRepaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        $source = $this->source instanceof LoanRepaymentSource
            ? $this->source
            : LoanRepaymentSource::tryFrom((string) $this->source);

        return [
            'id' => $this->id,
            'date' => $dates->toDisplay($this->date?->toDateString()),
            'amount' => (float) $this->amount,
            'currency_id' => $this->currency_id,
            'rate' => (float) $this->rate,
            // Distinguishes an instalment taken through payroll from cash
            // handed back from a write-off. The balance reaches zero either
            // way, and the statement has to be able to say which happened.
            'source' => $source?->value,
            'source_label' => $source?->getLabel(),
            'payroll_line_id' => $this->payroll_line_id,
            'transaction_id' => $this->transaction_id,
            'remark' => $this->remark,
        ];
    }
}

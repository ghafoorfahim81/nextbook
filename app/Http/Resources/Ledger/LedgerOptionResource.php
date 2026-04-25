<?php

namespace App\Http\Resources\Ledger;

use App\Enums\LedgerType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->type instanceof LedgerType ? $this->type->value : (string) $this->type;
        $totalDebit = (float) ($this->statement_total_debit ?? 0);
        $totalCredit = (float) ($this->statement_total_credit ?? 0);
        $netBalance = $totalDebit - $totalCredit;
        $balanceAmount = abs($netBalance);
        $balanceNature = $netBalance >= 0 ? 'dr' : 'cr';
        $normalNature = $type === 'supplier' ? 'cr' : 'dr';

        return [
            'id' => $this->id,
            'name' => $this->code ? $this->name.' - '.$this->code : $this->name,
            'code' => $this->code,
            'type' => $type,
            'email' => $this->email,
            'phone_no' => $this->phone_no,
            'address' => $this->address,
            'currency_id' => $this->currency_id,
            'is_active' => (bool) $this->is_active,
            'statement' => [
                'balance' => $balanceAmount,
                'balance_amount' => $balanceAmount,
                'balance_nature' => $balanceNature,
                'normal_balance_nature' => $normalNature,
                'is_normal_balance' => $balanceNature === $normalNature,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'net_balance' => $netBalance,
                'account_type' => $type,
                'payable_amount' => $balanceNature === 'cr' ? $balanceAmount : 0,
                'receivable_amount' => $balanceNature === 'dr' ? $balanceAmount : 0,
            ],
        ];
    }
}

<?php

namespace App\Http\Resources\Account;

use App\Http\Resources\LedgerOpening\LedgerOpeningResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'number' => $this->number,
            'account_type_id' => $this->account_type_id,
            'balance' => $this->transactions?->sum('amount')?? 0,
            'account_type' => $this->accountType,
            'parent'    => $this->parent,
            'branch_id' => $this->branch_id,
            'branch'    => $this->branch,
            'remark' => $this->remark,
        ];
    }
}

<?php

namespace App\Http\Resources\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;

class TransactionLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => new AccountResource($this->account)),
            'debit' => $this->debit,
            'credit' => $this->credit,
            'remark' => $this->remark,
        ];
    }
}

<?php

namespace App\Http\Resources\Account;

use App\Http\Resources\Ledger\LedgerOpeningResource;
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
            'balance' => $this->statement['balance'],
            'balance_with_nature' => $this->statement['balance_with_nature'],
            'account_type' => $this->accountType,
            'slug' => $this->slug,
            'is_active' => $this->is_active,
            'is_main' => $this->is_main,
            'parent'    => $this->parent,
            'branch_id' => $this->branch_id,
            'branch'    => $this->branch,
            'remark' => $this->remark,
            'openings' => LedgerOpeningResource::collection($this->whenLoaded('openings')),

        ];
    }
}

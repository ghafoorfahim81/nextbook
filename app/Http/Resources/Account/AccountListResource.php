<?php

namespace App\Http\Resources\Account;

use App\Http\Resources\Ledger\LedgerOpeningResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Transaction\TransactionLineResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
class AccountListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    { 
        return [
            'id' => $this->id, 
            'english_name' => $this->name, 
            'local_name' => $this->local_name,
            'number' => $this->number, 
            'balance' => $this->statement['balance'], 
            'account_type' => $this->accountType,   
            'parent'    => $this->whenLoaded('parent', fn() => new AccountResource($this->parent)),  
            'remark' => $this->remark,  
        ];
    }
}

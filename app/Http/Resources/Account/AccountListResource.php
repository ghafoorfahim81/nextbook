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
        $locale = app()->getLocale();
        return [
            'id' => $this->id, 
            'english_name' => $this->name, 
            'local_name' => $this->local_name,
            'name' => $locale === 'en' ? $this->name : $this->local_name,
            'number' => $this->number,
            'balance' => $this->statement['balance'],
            'balance_amount' => $this->statement['balance_amount'],
            'balance_nature' => $this->statement['balance_nature'],
            'balance_with_nature' => $this->statement['balance_with_nature'],
            'total_debit' => $this->statement['total_debit'],
            'total_credit' => $this->statement['total_credit'],
            'net_balance' => $this->statement['net_balance'],
            'account_type' => $this->accountType,   
            'parent'    => $this->whenLoaded('parent', fn() => new AccountResource($this->parent)),  
            'remark' => $this->remark,  
        ];
    }
}

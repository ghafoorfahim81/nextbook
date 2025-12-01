<?php

namespace App\Http\Resources\Expense;

use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        return [
            'id' => $this->id, 
            'date' => $dateConversionService->toDisplay($this->date),
            'remarks' => $this->remarks,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'currency_id' => $this->bankTransaction?->currency_id,
            'currency' => $this->bankTransaction?->currency,
            'bank_account_id' => $this->bankTransaction?->account_id,
            'bank_account' => $this->bankTransaction?->account,
            'expense_account_id' => $this->expenseTransaction?->account_id,
            'expense_account' => $this->expenseTransaction?->account,
            'rate' => $this->expenseTransaction?->rate,
            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? asset('storage/' . $this->attachment) : null,
            'details' => ExpenseDetailResource::collection($this->whenLoaded('details')),
            'total' => $this->whenLoaded('details', fn() => $this->details->sum('amount')),
            'base_total' => $this->whenLoaded('details', fn() => $this->details->sum('amount') * ($this->rate ?? 1)),
            'expense_transaction_id' => $this->expense_transaction_id,
            'expense_transaction' => $this->whenLoaded('expenseTransaction'),
            'bank_transaction_id' => $this->bank_transaction_id,
            'bank_transaction' => $this->whenLoaded('bankTransaction'),
        ];
    }
}


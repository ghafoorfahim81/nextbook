<?php

namespace App\Http\Resources\Expense;

use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        return [
            'id' => $this->id,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'remarks' => $this->remarks,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn() => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'currency_id' => $this->transaction?->currency_id,
            'currency' => $this->transaction?->currency,
            'bank_account_id' => $this->transaction?->lines?->firstWhere(fn($line) => $line->credit > 0)?->account_id,
            'expense_account_id' => $this->transaction?->lines?->firstWhere(fn($line) => $line->debit > 0)?->account_id,
            'bank_account' => new AccountResource($this->transaction?->lines?->firstWhere(fn($line) => $line->credit > 0)?->account),
            'expense_account' => new AccountResource($this->transaction?->lines?->firstWhere(fn($line) => $line->debit > 0)?->account),
            'rate' => $this->transaction?->rate,
            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? asset('storage/' . $this->attachment) : null,
            'details' => ExpenseDetailResource::collection($this->whenLoaded('details')),
            'total' => $this->whenLoaded('details', fn() => $this->details->sum('amount')),
            'base_total' => $this->whenLoaded('details', fn() => $this->details->sum('amount') * ($this->rate ?? 1)),
        ];
    }
}


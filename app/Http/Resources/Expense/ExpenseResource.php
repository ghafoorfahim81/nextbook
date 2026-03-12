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
            'currency_id' => $this->transaction?->currency_id,
            'currency' => $this->transaction?->currency,
            'bank_account_id' => $this->transaction?->lines->last()?->account_id,
            'bank_account' => $this->transaction?->lines->last()?->account,
            'expense_account_id' => $this->transaction?->lines->first()?->account_id,
            'expense_account' => $this->transaction?->lines->first()?->account,
            'rate' => $this->transaction?->rate,
            'attachment' => $this->attachment,
            'attachment_url' => $this->attachment ? asset('storage/' . $this->attachment) : null,
            'details' => ExpenseDetailResource::collection($this->whenLoaded('details')),
            'total' => $this->whenLoaded('details', fn() => $this->details->sum('amount')),
            'base_total' => $this->whenLoaded('details', fn() => $this->details->sum('amount') * ($this->rate ?? 1)),
        ];
    }
}


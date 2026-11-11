<?php

namespace App\Http\Requests\Ledger\Concerns;

use App\Support\CompanyContext;
use Illuminate\Validation\Rule;

/**
 * A name is taken only by another party of the same kind. One person can be a
 * customer and a supplier — two ledgers, the same name — so the check has to
 * match the database's own rule: unique per company *and type*, among live rows.
 */
trait ValidatesPartyName
{
    /**
     * The party type this request is for, from the page it was posted to.
     */
    protected function partyType(): ?string
    {
        return match (true) {
            $this->is('suppliers', 'suppliers/*') => 'supplier',
            $this->is('customers', 'customers/*') => 'customer',
            default => null,
        };
    }

    /**
     * @return array<int, mixed>
     */
    protected function nameRules(): array
    {
        $rules = ['required', 'string'];

        $type = $this->partyType();

        if ($type === null) {
            return $rules;
        }

        $current = $this->route('supplier') ?? $this->route('customer') ?? $this->route('ledger');
        $currentId = is_object($current) ? $current->getKey() : $current;

        $rules[] = Rule::unique('ledgers', 'name')
            ->where(fn ($query) => $query
                ->where('company_id', CompanyContext::companyId())
                ->where('type', $type)
                ->whereNull('deleted_at'))
            ->ignore($currentId);

        return $rules;
    }
}

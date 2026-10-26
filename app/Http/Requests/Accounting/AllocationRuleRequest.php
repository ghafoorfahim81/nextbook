<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Concerns\BranchScopedUnique;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Creating an allocation rule, or editing one and adding a version of its
 * split (phase 7).
 *
 * A new rule needs its first version. An edit may leave the split alone (no
 * shares sent) or add a version: the shares from a date, which replace that
 * date's version if there is one. A version's shares add up to 100% exactly,
 * one share per branch.
 */
class AllocationRuleRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rule = $this->route('allocation_rule');
        $creating = $rule === null;

        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueInCompany('allocation_rules', $rule)],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'effective_from' => [$creating ? 'required' : 'required_with:shares', 'nullable', 'date'],
            'shares' => [$creating ? 'required' : 'nullable', 'array', 'min:1'],
            // branches has no row level security (it is read before sign-in),
            // so the company is checked here: a share names this company's branch.
            'shares.*.branch_id' => [
                'required', 'string', 'distinct',
                Rule::exists('branches', 'id')->where('company_id', CompanyContext::companyId())->whereNull('deleted_at'),
            ],
            'shares.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $shares = $this->input('shares');

                if (! is_array($shares) || $shares === [] || $validator->errors()->hasAny(['shares', 'shares.*'])) {
                    return;
                }

                $total = array_reduce($shares, fn ($sum, $share) => bcadd($sum, (string) ($share['percentage'] ?? 0), 4), '0');

                if (bccomp($total, '100', 4) !== 0) {
                    $validator->errors()->add('shares', __('messages.allocation.must_total_100', ['total' => rtrim(rtrim($total, '0'), '.')]));
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'shares.*.branch_id' => __('general.branch'),
            'shares.*.percentage' => __('general.percentage'),
        ];
    }
}

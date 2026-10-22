<?php

namespace App\Http\Requests\Concerns;

use App\Support\CompanyContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Uniqueness rules scoped to the branch the request is acting on.
 *
 * The string form these replace — `unique:items,name,NULL,id,branch_id,NULL,deleted_at,NULL`
 * — reads as "unique within the branch", but Laravel turns the NULL value into
 * `whereNull('branch_id')`. Every row has a branch, so the check ran against an empty
 * set and passed for every duplicate.
 */
trait BranchScopedUnique
{
    /**
     * @param  mixed  $ignore  record (or id) to exclude, for update requests
     */
    protected function uniqueInBranch(string $table, mixed $ignore = null, ?string $column = null): Unique
    {
        $rule = ($column === null ? Rule::unique($table) : Rule::unique($table, $column))
            ->whereNull('deleted_at');

        $branchId = $this->activeBranchId();

        if ($branchId !== null) {
            $rule->where('branch_id', $branchId);
        }

        return $ignore ? $rule->ignore($ignore) : $rule;
    }

    /**
     * The branch this request is acting on. Never read from request input — that
     * would let the caller pick which branch their uniqueness is checked against.
     */
    protected function activeBranchId(): ?string
    {
        if (app()->bound('active_branch_id')) {
            $branchId = app('active_branch_id');

            if ($branchId) {
                return (string) $branchId;
            }
        }

        $branchId = CompanyContext::branchId();

        return $branchId ? (string) $branchId : null;
    }

    /**
     * An `exists` rule confined to the acting branch.
     *
     * A bare `exists:employees,id` passes for ANY branch's row. Eloquent's
     * BranchSpecific scope does not help: the validator queries the table
     * directly, so a crafted request can point a leave request, a loan or a
     * payroll at another tenant's employee and the foreign key is accepted.
     * The policy check does not catch it either — that authorises the ACTION,
     * not the id inside the payload.
     */
    protected function existsInBranch(string $table, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column)->whereNull('deleted_at');

        $branchId = $this->activeBranchId();

        if ($branchId !== null) {
            $rule->where('branch_id', $branchId);
        }

        return $rule;
    }

    /**
     * The company counterpart of uniqueInBranch, for master data.
     *
     * Chart of accounts, catalogue, customers and HR configuration are shared
     * by every branch of a company, so their uniqueness is a company question:
     * one item code per company, not per branch.
     */
    protected function uniqueInCompany(string $table, mixed $ignore = null, ?string $column = null): Unique
    {
        $rule = ($column === null ? Rule::unique($table) : Rule::unique($table, $column))
            ->whereNull('deleted_at');

        $companyId = $this->activeCompanyId();

        if ($companyId !== null) {
            $rule->where('company_id', $companyId);
        }

        return $ignore ? $rule->ignore($ignore) : $rule;
    }

    /**
     * An `exists` rule confined to the acting company, for the same reasons
     * existsInBranch exists: the validator queries the table directly, so a
     * crafted request can otherwise point at another company's row.
     */
    protected function existsInCompany(string $table, string $column = 'id'): Exists
    {
        $rule = Rule::exists($table, $column)->whereNull('deleted_at');

        $companyId = $this->activeCompanyId();

        if ($companyId !== null) {
            $rule->where('company_id', $companyId);
        }

        return $rule;
    }

    /**
     * The company this request is acting in. Never read from request input,
     * for the reason given on activeBranchId.
     */
    protected function activeCompanyId(): ?string
    {
        if (app()->bound('active_company_id')) {
            $companyId = app('active_company_id');

            if ($companyId) {
                return (string) $companyId;
            }
        }

        $companyId = CompanyContext::companyId();

        return $companyId ? (string) $companyId : null;
    }
}

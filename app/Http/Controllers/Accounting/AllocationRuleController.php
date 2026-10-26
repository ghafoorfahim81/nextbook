<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\AllocationRuleRequest;
use App\Models\Accounting\AllocationRule;
use App\Models\Administration\Branch;
use App\Services\DateConversionService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Allocation rules (phase 7): how a shared cost is split across branches,
 * dated so a change never rewrites the past.
 *
 * The edit page keeps every earlier version on view and adds new ones; an
 * expense already posted keeps the split it was posted with, since the parts
 * are on its ledger lines.
 */
class AllocationRuleController extends Controller
{
    public function __construct(private DateConversionService $dates)
    {
        $this->authorizeResource(AllocationRule::class, 'allocation_rule');
    }

    public function index(Request $request)
    {
        $rules = AllocationRule::query()
            ->search($request->query('search'))
            ->orderBy($request->input('sortField', 'name'), $request->input('sortDirection', 'asc'))
            ->paginate($request->input('perPage', recordsPerPage()))
            ->withQueryString();

        return inertia('Accounting/AllocationRules/Index', [
            'rules' => $rules->through(fn (AllocationRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'description' => $rule->description,
                'is_active' => $rule->is_active,
                'current' => $this->summary($rule, now()->toDateString()),
            ]),
        ]);
    }

    public function create()
    {
        return inertia('Accounting/AllocationRules/Form', [
            'rule' => null,
            'branches' => $this->branches(),
        ]);
    }

    public function store(AllocationRuleRequest $request)
    {
        $data = $request->validated();

        $rule = DB::transaction(function () use ($data) {
            $rule = AllocationRule::create($data);
            $this->saveVersion($rule, $data['effective_from'], $data['shares']);

            return $rule;
        });

        return redirect()->route('allocation-rules.edit', $rule)
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.allocation_rule')]));
    }

    public function show(AllocationRule $allocationRule)
    {
        return redirect()->route('allocation-rules.edit', $allocationRule);
    }

    public function edit(AllocationRule $allocationRule)
    {
        return inertia('Accounting/AllocationRules/Form', [
            'rule' => [
                'id' => $allocationRule->id,
                'name' => $allocationRule->name,
                'description' => $allocationRule->description,
                'is_active' => $allocationRule->is_active,
                'versions' => $allocationRule->versions()->map(fn ($shares, $date) => [
                    'effective_from' => $this->dates->toDisplay($date),
                    'shares' => $shares->map(fn ($share) => [
                        'branch_id' => $share->branch_id,
                        'branch' => $share->branch?->name,
                        'percentage' => (float) $share->percentage,
                    ])->values(),
                ])->values(),
            ],
            'branches' => $this->branches(),
        ]);
    }

    public function update(AllocationRuleRequest $request, AllocationRule $allocationRule)
    {
        $data = $request->validated();

        DB::transaction(function () use ($allocationRule, $data) {
            $allocationRule->update($data);

            if (! empty($data['shares'])) {
                $this->saveVersion($allocationRule, $data['effective_from'], $data['shares']);
            }
        });

        return back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.allocation_rule')]));
    }

    public function destroy(AllocationRule $allocationRule)
    {
        if (! $allocationRule->canBeDeleted()) {
            return back()->with('error', $allocationRule->getDependencyMessage() ?? __('messages.dependency.cannot_delete_generic'));
        }

        $allocationRule->delete();

        return redirect()->route('allocation-rules.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.allocation_rule')]));
    }

    /** A version: the shares from a date, replacing that date's if it exists. */
    private function saveVersion(AllocationRule $rule, string $effectiveFrom, array $shares): void
    {
        $date = $this->dates->toGregorian($effectiveFrom);

        $rule->shares()->whereDate('effective_from', $date)->delete();

        foreach ($shares as $share) {
            $rule->shares()->create([
                'company_id' => $rule->company_id,
                'branch_id' => $share['branch_id'],
                'effective_from' => $date,
                'percentage' => $share['percentage'],
            ]);
        }
    }

    /** "Kabul 50% · Herat 30% · Mazar 20%" for the version in force on a date. */
    private function summary(AllocationRule $rule, string $date): ?string
    {
        $shares = $rule->sharesOn($date)->load('branch:id,name');

        return $shares->isEmpty()
            ? null
            : $shares->map(fn ($share) => ($share->branch?->name ?? '?') . ' ' . rtrim(rtrim((string) $share->percentage, '0'), '.') . '%')->implode(' · ');
    }

    private function branches()
    {
        return Branch::query()
            ->withoutGlobalScopes()
            ->where('company_id', CompanyContext::companyId())
            ->whereNull('deleted_at')
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}

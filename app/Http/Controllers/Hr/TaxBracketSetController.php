<?php

namespace App\Http\Controllers\Hr;

use App\Enums\TaxPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\TaxBracketSetStoreRequest;
use App\Http\Requests\Hr\TaxBracketSetUpdateRequest;
use App\Http\Resources\Hr\TaxBracketSetResource;
use App\Models\Administration\Currency;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Services\Hr\WageTaxService;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The wage-tax tables.
 *
 * These ship seeded with the Afghan monthly brackets, but they are DATA, not
 * rules baked into the code — a rate change is a form the user fills in, not a
 * release. Effective-dating is what makes that safe: an old period re-run
 * after a change still resolves the table that was in force at the time.
 */
class TaxBracketSetController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(TaxBracketSet::class, 'tax_bracket_set');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sets = TaxBracketSet::query()
            ->with(['currency:id,code', 'brackets', 'createdBy:id,name'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('effective_from', $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/TaxBracketSets/Index', [
            'taxBracketSets' => TaxBracketSetResource::collection($sets),
            'filterOptions' => [
                'periods' => array_map(
                    fn (TaxPeriod $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                    TaxPeriod::cases()
                ),
                'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
            ],
            // Offered as a starting point for a new table, so a user correcting
            // a rate edits real figures rather than typing four bands blind.
            'defaultBrackets' => TaxBracketSet::defaultAfghanMonthlyBrackets(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'effective_from',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function store(TaxBracketSetStoreRequest $request)
    {
        DB::transaction(function () use ($request) {
            $data = $request->validated();
            $brackets = $data['brackets'];
            unset($data['brackets']);

            $set = TaxBracketSet::create($data);
            $this->syncBrackets($set, $brackets);
        });

        return redirect()->back()->with('success', __('general.created_successfully', [
            'resource' => __('hr.tax_bracket_set'),
        ]));
    }

    public function update(TaxBracketSetUpdateRequest $request, TaxBracketSet $taxBracketSet)
    {
        DB::transaction(function () use ($request, $taxBracketSet) {
            $data = $request->validated();
            $brackets = $data['brackets'];
            unset($data['brackets']);

            $taxBracketSet->update($data);
            $this->syncBrackets($taxBracketSet, $brackets);
        });

        return redirect()->back()->with('success', __('general.updated_successfully', [
            'resource' => __('hr.tax_bracket_set'),
        ]));
    }

    /**
     * What this table would deduct from a given income.
     *
     * A rate table is hard to read and easy to get subtly wrong, so the form
     * offers a live figure. Runs against the UNSAVED brackets on the form —
     * the point is to check them before committing.
     */
    public function preview(Request $request, WageTaxService $wageTax)
    {
        $this->authorize('viewAny', TaxBracketSet::class);

        $validated = $request->validate([
            'income' => ['required', 'numeric', 'min:0'],
            'brackets' => ['required', 'array', 'min:1'],
            'brackets.*.from_amount' => ['required', 'numeric', 'min:0'],
            'brackets.*.to_amount' => ['nullable', 'numeric'],
            'brackets.*.fixed_amount' => ['required', 'numeric', 'min:0'],
            'brackets.*.rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $result = $wageTax->computeAgainst($validated['income'], $validated['brackets']);

        return response()->json([
            'income' => Decimal::amount($validated['income']),
            'tax' => $result['tax'],
            'net' => Decimal::sub(Decimal::amount($validated['income']), $result['tax']),
            'marginal_rate' => $result['marginal_rate'],
        ]);
    }

    public function destroy(Request $request, TaxBracketSet $taxBracketSet)
    {
        // A table a payslip was computed with must stay readable, or the
        // payslip can no longer explain its own tax figure.
        $inUse = DB::table('payroll_lines')
            ->where('tax_bracket_set_id', $taxBracketSet->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($inUse) {
            return redirect()->back()->with('error', __('hr.tax_table_in_use'));
        }

        $taxBracketSet->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', [
            'resource' => __('hr.tax_bracket_set'),
        ]));
    }

    public function restore(Request $request, TaxBracketSet $taxBracketSet)
    {
        $this->authorize('update', $taxBracketSet);
        $taxBracketSet->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', [
            'resource' => __('hr.tax_bracket_set'),
        ]));
    }

    /**
     * Replace the bands wholesale.
     *
     * Deleted and rebuilt rather than diffed: the bands are meaningful only as
     * a contiguous set, and a partial update could leave a gap between one
     * saved band and one not yet saved.
     *
     * @param  array<int, array<string, mixed>>  $brackets
     */
    private function syncBrackets(TaxBracketSet $set, array $brackets): void
    {
        TaxBracket::withoutGlobalScope('branchSpecific')
            ->where('tax_bracket_set_id', $set->id)
            ->forceDelete();

        foreach ($brackets as $index => $bracket) {
            TaxBracket::create([
                'tax_bracket_set_id' => $set->id,
                'sequence' => $bracket['sequence'] ?? $index + 1,
                'from_amount' => $bracket['from_amount'],
                'to_amount' => $bracket['to_amount'] ?? null,
                'fixed_amount' => $bracket['fixed_amount'],
                'rate' => $bracket['rate'],
                'branch_id' => $set->branch_id,
            ]);
        }
    }
}

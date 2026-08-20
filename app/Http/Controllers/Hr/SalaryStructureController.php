<?php

namespace App\Http\Controllers\Hr;

use App\Enums\ComponentCalculationType;
use App\Enums\PayFrequency;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SalaryStructureStoreRequest;
use App\Http\Requests\Hr\SalaryStructureUpdateRequest;
use App\Http\Resources\Hr\SalaryComponentResource;
use App\Http\Resources\Hr\SalaryStructureResource;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Administration\Designation;
use App\Models\Hr\SalaryComponent;
use App\Models\Hr\SalaryStructure;
use App\Models\Hr\SalaryStructureLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Salary packages, effective-dated.
 *
 * A structure is never edited in place for a pay rise — a new one is created
 * with a later effective_from, so an old payroll re-run still resolves the
 * package that was in force at the time.
 */
class SalaryStructureController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(SalaryStructure::class, 'salary_structure');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $structures = SalaryStructure::query()
            ->with([
                'employee:id,full_name,code,branch_id',
                'designation:id,name,branch_id',
                'department:id,name,branch_id',
                'currency:id,code',
                'createdBy:id,name',
            ])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('effective_from', $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/SalaryStructures/Index', [
            'salaryStructures' => SalaryStructureResource::collection($structures),
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'effective_from',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function create()
    {
        return inertia('Hr/SalaryStructures/Create', [
            'filterOptions' => $this->filterOptions(),
            'components' => SalaryComponentResource::collection(
                SalaryComponent::query()->where('is_active', true)->orderBy('sequence')->get()
            ),
        ]);
    }

    public function edit(SalaryStructure $salaryStructure)
    {
        $salaryStructure->load([
            'employee:id,full_name,code,branch_id',
            'currency:id,code',
            'lines.component:id,name,code,component_type,branch_id',
        ]);

        return inertia('Hr/SalaryStructures/Edit', [
            'salaryStructure' => new SalaryStructureResource($salaryStructure),
            'filterOptions' => $this->filterOptions(),
            'components' => SalaryComponentResource::collection(
                SalaryComponent::query()->where('is_active', true)->orderBy('sequence')->get()
            ),
        ]);
    }

    public function show(SalaryStructure $salaryStructure)
    {
        $salaryStructure->load([
            'employee:id,full_name,code,branch_id',
            'designation:id,name,branch_id',
            'department:id,name,branch_id',
            'currency:id,code',
            'lines.component:id,name,code,component_type,branch_id',
            'createdBy:id,name',
        ]);

        return inertia('Hr/SalaryStructures/Show', [
            'salaryStructure' => new SalaryStructureResource($salaryStructure),
        ]);
    }

    public function store(SalaryStructureStoreRequest $request)
    {
        $structure = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $structure = SalaryStructure::create($data);
            $this->syncLines($structure, $lines);

            return $structure;
        });

        return redirect()->route('salary-structures.show', $structure)
            ->with('success', __('general.created_successfully', [
                'resource' => __('hr.salary_structure'),
            ]));
    }

    public function update(SalaryStructureUpdateRequest $request, SalaryStructure $salaryStructure)
    {
        DB::transaction(function () use ($request, $salaryStructure) {
            $data = $request->validated();
            $lines = $data['lines'] ?? [];
            unset($data['lines']);

            $salaryStructure->update($data);
            $this->syncLines($salaryStructure, $lines);
        });

        return redirect()->route('salary-structures.show', $salaryStructure)
            ->with('success', __('general.updated_successfully', [
                'resource' => __('hr.salary_structure'),
            ]));
    }

    public function destroy(Request $request, SalaryStructure $salaryStructure)
    {
        // A structure a payslip was computed from must stay readable, or the
        // payslip can no longer explain where its figures came from.
        $inUse = DB::table('payroll_lines')
            ->where('salary_structure_id', $salaryStructure->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($inUse) {
            return redirect()->back()->with('error', __('hr.structure_in_use'));
        }

        $salaryStructure->delete();

        return redirect()->route('salary-structures.index')
            ->with('success', __('general.deleted_successfully', [
                'resource' => __('hr.salary_structure'),
            ]));
    }

    public function restore(Request $request, SalaryStructure $salaryStructure)
    {
        $this->authorize('update', $salaryStructure);
        $salaryStructure->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', [
            'resource' => __('hr.salary_structure'),
        ]));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(SalaryStructure $structure, array $lines): void
    {
        SalaryStructureLine::query()
            ->where('salary_structure_id', $structure->id)
            ->forceDelete();

        // The form may leave calculation_type blank to mean "however this
        // component normally works", so the fallback has to be READ from the
        // component. Passing null through would not fall back to the column
        // default — an explicit null overrides a default and violates NOT NULL.
        $components = SalaryComponent::query()
            ->whereIn('id', array_column($lines, 'salary_component_id'))
            ->get()
            ->keyBy('id');

        foreach ($lines as $index => $line) {
            $component = $components->get($line['salary_component_id']);

            SalaryStructureLine::create([
                'salary_structure_id' => $structure->id,
                'salary_component_id' => $line['salary_component_id'],
                'calculation_type' => $line['calculation_type']
                    ?? $component?->calculation_type?->value
                    ?? ComponentCalculationType::Fixed->value,
                'amount' => $line['amount'] ?? $component?->amount,
                'percentage' => $line['percentage'] ?? $component?->percentage,
                'sequence' => $line['sequence'] ?? $index + 1,
                'branch_id' => $structure->branch_id,
            ]);
        }
    }

    private function filterOptions(): array
    {
        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
            'payFrequencies' => array_map(
                fn (PayFrequency $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                PayFrequency::cases()
            ),
        ];
    }
}

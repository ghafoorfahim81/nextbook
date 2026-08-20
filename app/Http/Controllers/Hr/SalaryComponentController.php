<?php

namespace App\Http\Controllers\Hr;

use App\Enums\ComponentCalculationType;
use App\Enums\SalaryComponentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SalaryComponentStoreRequest;
use App\Http\Requests\Hr\SalaryComponentUpdateRequest;
use App\Http\Resources\Hr\SalaryComponentResource;
use App\Models\Hr\SalaryComponent;
use Illuminate\Http\Request;

class SalaryComponentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(SalaryComponent::class, 'salary_component');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        $components = SalaryComponent::query()
            ->with(['account:id,name', 'createdBy:id,name'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('sequence', $sortDirection)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/SalaryComponents/Index', [
            'salaryComponents' => SalaryComponentResource::collection($components),
            'filterOptions' => [
                'componentTypes' => array_map(
                    fn (SalaryComponentType $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                    SalaryComponentType::cases()
                ),
                'calculationTypes' => array_map(
                    fn (ComponentCalculationType $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                    ComponentCalculationType::cases()
                ),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'sequence',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function store(SalaryComponentStoreRequest $request)
    {
        SalaryComponent::create($request->validated());

        return redirect()->back()->with('success', __('general.created_successfully', [
            'resource' => __('hr.salary_component'),
        ]));
    }

    public function update(SalaryComponentUpdateRequest $request, SalaryComponent $salaryComponent)
    {
        $data = $request->validated();

        // The code is what PayrollCalculationService matches on to find BASIC,
        // OVERTIME and WITHHOLDING_TAX. Renaming a system component is fine;
        // re-CODING one would silently detach it from the engine.
        if ($salaryComponent->is_system) {
            unset($data['code'], $data['component_type']);
        }

        $salaryComponent->update($data);

        return redirect()->back()->with('success', __('general.updated_successfully', [
            'resource' => __('hr.salary_component'),
        ]));
    }

    public function destroy(Request $request, SalaryComponent $salaryComponent)
    {
        if ($salaryComponent->is_system) {
            return redirect()->back()->with('error', __('hr.system_component_cannot_be_deleted'));
        }

        if (! $salaryComponent->canBeDeleted()) {
            return redirect()->back()->with('error', $salaryComponent->getDependencyMessage());
        }

        $salaryComponent->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', [
            'resource' => __('hr.salary_component'),
        ]));
    }

    public function restore(Request $request, SalaryComponent $salaryComponent)
    {
        $this->authorize('update', $salaryComponent);
        $salaryComponent->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', [
            'resource' => __('hr.salary_component'),
        ]));
    }

    public function forceDelete(Request $request, SalaryComponent $salaryComponent)
    {
        $this->authorize('delete', $salaryComponent);

        if ($salaryComponent->is_system) {
            return redirect()->back()->with('error', __('hr.system_component_cannot_be_deleted'));
        }

        $salaryComponent->forceDelete();

        return redirect()->back()->with('success', __('general.deleted_successfully', [
            'resource' => __('hr.salary_component'),
        ]));
    }
}

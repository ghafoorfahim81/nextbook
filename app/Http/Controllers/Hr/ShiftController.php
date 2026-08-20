<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\ShiftStoreRequest;
use App\Http\Requests\Hr\ShiftUpdateRequest;
use App\Http\Resources\Hr\ShiftResource;
use App\Models\Hr\Shift;
use App\Services\DeletedRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShiftController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Shift::class, 'shift');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';
        $sortColumn = ['name' => 'name', 'code' => 'code'][$request->input('sortField', 'name')] ?? 'name';

        $shifts = Shift::query()
            ->withCount('employees')
            ->with('createdBy:id,name')
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/Shifts/Index', [
            'shifts' => ShiftResource::collection($shifts),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $request->input('sortField', 'name'),
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function store(ShiftStoreRequest $request)
    {
        DB::transaction(function () use ($request) {
            $shift = Shift::create($request->validated());
            $this->demoteOtherDefaults($shift);
        });

        return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('hr.shift')]));
    }

    public function update(ShiftUpdateRequest $request, Shift $shift)
    {
        DB::transaction(function () use ($request, $shift) {
            $shift->update($request->validated());
            $this->demoteOtherDefaults($shift);
        });

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('hr.shift')]));
    }

    public function destroy(Request $request, Shift $shift)
    {
        if (! $shift->canBeDeleted()) {
            return redirect()->back()->with('error', $shift->getDependencyMessage());
        }

        $shift->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', ['resource' => __('hr.shift')]));
    }

    public function restore(Request $request, Shift $shift)
    {
        $this->authorize('update', $shift);
        $shift->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', ['resource' => __('hr.shift')]));
    }

    public function forceDelete(Request $request, Shift $shift)
    {
        $this->authorize('delete', $shift);
        app(DeletedRecordService::class)->forceDelete('shifts', (string) $shift->id);

        return redirect()->back()->with('success', __('general.permanently_deleted_successfully', ['resource' => __('hr.shift')]));
    }

    /**
     * Exactly one default shift per branch — it is what new employees and the
     * punch pairer fall back to, so two would make that fallback arbitrary.
     */
    private function demoteOtherDefaults(Shift $shift): void
    {
        if (! $shift->is_default) {
            return;
        }

        Shift::query()->where('id', '!=', $shift->id)->where('is_default', true)
            ->update(['is_default' => false]);
    }
}

<?php

namespace App\Http\Controllers\Hr;

use App\Enums\HolidayType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\HolidayStoreRequest;
use App\Http\Requests\Hr\HolidayUpdateRequest;
use App\Http\Resources\Hr\HolidayResource;
use App\Models\Hr\Holiday;
use App\Services\DateConversionService;
use App\Services\DeletedRecordService;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    private DateConversionService $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Holiday::class, 'holiday');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        $holidays = Holiday::query()
            ->with('createdBy:id,name')
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('date', $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/Holidays/Index', [
            'holidays' => HolidayResource::collection($holidays),
            'filterOptions' => [
                'holidayTypes' => array_map(
                    fn (HolidayType $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                    HolidayType::cases()
                ),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'date',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function store(HolidayStoreRequest $request)
    {
        Holiday::create($this->normalizeDates($request->validated()));

        return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('hr.holiday')]));
    }

    public function update(HolidayUpdateRequest $request, Holiday $holiday)
    {
        $holiday->update($this->normalizeDates($request->validated()));

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('hr.holiday')]));
    }

    public function destroy(Request $request, Holiday $holiday)
    {
        $holiday->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', ['resource' => __('hr.holiday')]));
    }

    public function restore(Request $request, Holiday $holiday)
    {
        $this->authorize('update', $holiday);
        $holiday->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', ['resource' => __('hr.holiday')]));
    }

    public function forceDelete(Request $request, Holiday $holiday)
    {
        $this->authorize('delete', $holiday);
        app(DeletedRecordService::class)->forceDelete('holidays', (string) $holiday->id);

        return redirect()->back()->with('success', __('general.permanently_deleted_successfully', ['resource' => __('hr.holiday')]));
    }

    private function normalizeDates(array $validated): array
    {
        foreach (['date', 'end_date'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = $this->dateConversionService->toGregorian($validated[$field]);
            }
        }

        return $validated;
    }
}

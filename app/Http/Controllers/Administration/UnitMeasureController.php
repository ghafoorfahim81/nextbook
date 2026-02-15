<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\UnitMeasureStoreRequest;
use App\Http\Requests\Administration\UnitMeasureUpdateRequest;
use App\Http\Resources\Administration\UnitMeasureCollection;
use App\Http\Resources\Administration\UnitMeasureResource;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Quantity;
use App\Http\Resources\Administration\QuantityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
class UnitMeasureController extends Controller
{
    protected $metric;

    public function __construct()
    {
        $this->authorizeResource(UnitMeasure::class, 'unit_measure');
        $this->metric = new Quantity();
    }
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $unitMeasures = UnitMeasure::with(['quantity', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->where('is_active', true)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        $quantities = Quantity::with('measures')
            ->orderBy('quantity')
            ->get();
        return inertia('Administration/UnitMeasures/Index', [
            'unitMeasures' => UnitMeasureResource::collection($unitMeasures),
            'quantities' => QuantityResource::collection($quantities),
        ]);
    }

    public function store(UnitMeasureStoreRequest $request)
    {
        try {
            DB::beginTransaction();
            $metricType = $request['metric'];
            $measure    = $request['measure'];

            // Create flattened data for validation and model creation
            $measureData = [
                'name' => $measure['name'],
                'unit' => $measure['unit'],
                'symbol' => $measure['symbol'],
                'branch_id' => Auth::user()->branch_id ?? 1,
                'created_by' => Auth::id(),
                'updated_by' => null,
            ];

            // Validate the nested structure
            $request->validate([
                'metric' => 'required|array',
                'metric.name' => 'required|string',
                'metric.unit' => 'required|string',
                'metric.symbol' => 'required|string',
                'measure' => 'required|array',
                'measure.name' => 'required|string',
                'measure.unit' => 'required|numeric',
                'measure.symbol' => 'required|string',
            ]);

            // Set required fields for the model after validation
            $request->offsetSet('name', $measure['name']);
            $request->offsetSet('unit', $measure['unit']);
            $request->offsetSet('symbol', $measure['symbol']);
            $request->offsetSet('branch_id', Auth::user()->branch_id ?? 1);
            $request->offsetSet('created_by', Auth::id());


            $metric = $this->metric->where('unit', $metricType['unit'])->first();

            if ($metric == null) {
                $metric = $this->metric->create(attributes: [
                    'quantity' => $metricType['name'],
                    'unit' => $metricType['unit'],
                    'symbol' => $metricType['symbol'],
                    'slug' => Str::slug($metricType['name']),
                    'is_main' => false,
                    'created_by' => Auth::id()
                ]);
            }

            // Create measure data array
            $measureData = [
                'name' => $measure['name'],
                'unit' => $measure['unit'],
                'symbol' => $measure['symbol'],
                'branch_id' => Auth::user()->branch_id ?? 1,
                'quantity_id' => $metric->id,
                'is_main' => false,
                'created_by' => Auth::id(),
                'updated_by' => null,
            ];

            // Create the measure (duplicate checking is handled by form request validation)
            $metric->measures()->create(attributes: $measureData);

            Cache::forget('unitMeasures');
            DB::commit();
            return redirect()->route('unit-measures.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.unit_measure')]));
        } catch (\Throwable $th) {
            DB::rollBack();
            return back()->withErrors(['general' => __('general.error_occurred_while_creating', ['resource' => __('general.resource.unit_measure')])])->withInput();
        }
    }

    public function show(Request $request, UnitMeasure $unitMeasure): UnitMeasureResource
    {
        $unitMeasure->load(['quantity', 'createdBy', 'updatedBy']);
        return new UnitMeasureResource($unitMeasure);
    }


    public function update(UnitMeasureUpdateRequest $request, UnitMeasure $unitMeasure)
    {
        try {
            DB::beginTransaction();

            $metricType = $request['metric'];
            $measure = $request['measure'];

            // Transform the nested data to flat structure for validation
            $request->offsetSet('name', $measure['name'] ?? null);
            $request->offsetSet('unit', $measure['unit'] ?? null);
            $request->offsetSet('symbol', $measure['symbol'] ?? null);
            $request->offsetSet('quantity_id', null); // Will be set after we find/create the quantity
            $request->offsetSet('branch_id', Auth::user()->branch_id ?? 1); // Get from authenticated user
            $request->offsetSet('created_by', $unitMeasure->created_by); // Keep original creator
            $request->offsetSet('updated_by', Auth::id());

            $metric = $this->metric->where('unit', $metricType['unit'])->first();

            if ($metric == null) {
                $metric = $this->metric->create([
                    'quantity' => $metricType['name'],
                    'unit' => $metricType['unit'],
                    'symbol' => $metricType['symbol'],
                    'description' => $metricType['description'],
                    'is_main' => false,
                    'created_by' => Auth::id()
                ]);
            }

            // Set the quantity_id now that we have the metric
            $request->offsetSet('quantity_id', $metric->id);

            // Update the existing measure (duplicate checking is handled by form request validation)
            $unitMeasure->update([
                'name' => $measure['name'],
                'unit' => $measure['unit'],
                'symbol' => $measure['symbol'],
                'description' => $measure['description'],
                'quantity_id' => $metric->id,
                'is_main' => false,
                'updated_by' => Auth::id()
            ]);

            DB::commit();
            Cache::forget('unitMeasures');
            return redirect()->route('unit-measures.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.unit_measure')]));
        } catch (\Throwable $th) {
            DB::rollBack();
            return back()->withErrors(['general' => __('general.error_occurred_while_updating', ['resource' => __('general.resource.unit_measure')])])->withInput();
        }
    }

    public function destroy(Request $request, UnitMeasure $unitMeasure)
    {
        // Check for dependencies before deletion
        if (!$unitMeasure->canBeDeleted()) {
            $message = $unitMeasure->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return inertia('Administration/UnitMeasures/Index', [
                'error' => $message
            ]);
        }

        $unitMeasure->delete();

        Cache::forget('unitMeasures');
        return redirect()->route('unit-measures.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.unit_measure')]));
    }

    public function restore(Request $request, UnitMeasure $unitMeasure)
    {
        $unitMeasure->restore();
        Cache::forget('unitMeasures');
        return redirect()->route('unit-measures.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.unit_measure')]));
    }
}

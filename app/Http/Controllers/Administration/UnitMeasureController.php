<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\UnitMeasureStoreRequest;
use App\Http\Requests\Administration\UnitMeasureUpdateRequest;
use App\Http\Resources\Administration\UnitMeasureCollection;
use App\Http\Resources\Administration\UnitMeasureResource;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Quantity;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UnitMeasureController extends Controller
{
    protected $metric;

    public function __construct()
    {
        $this->metric = new Quantity();
    }
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $unitMeasures = UnitMeasure::search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/UnitMeasures/Index', [
            'unitMeasures' => UnitMeasureResource::collection($unitMeasures),
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
                    'is_system' => false,
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
                'is_system' => false,
                'created_by' => Auth::id(),
                'updated_by' => null,
            ];

            // Create the measure (duplicate checking is handled by form request validation)
            $metric->measures()->create(attributes: $measureData);


            DB::commit();
            return redirect()->route('unit-measures.index')->with('success', 'Unit measure created successfully.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return back()->withErrors(['general' => 'An error occurred while creating the measure.'])->withInput();
        }
    }

    public function show(Request $request, UnitMeasure $unitMeasure): UnitMeasureResource
    {
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
                    'is_system' => false,
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
                'is_system' => false,
                'updated_by' => Auth::id()
            ]);

            DB::commit();
            return redirect()->route('unit-measures.index')->with('success', 'Unit measure updated successfully.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return back()->withErrors(['general' => 'An error occurred while updating the measure.'])->withInput();
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

        return redirect()->route('unit-measures.index')->with('success', 'Unit measure deleted successfully.');
    }

    public function restore(Request $request, UnitMeasure $unitMeasure)
    {
        $unitMeasure->restore();
        return redirect()->route('unit-measures.index')->with('success', 'Unit measure restored successfully.');
    }
}

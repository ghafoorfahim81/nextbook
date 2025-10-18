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

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $metricType = $request->metric;
            $measure    = $request->measure;

            if($measure['unit'] <= 0) {
                return redirect()->route('unit-measures.index')->with('error', 'Unit must be positive number');
            }

            $metric = $this->metric->where('unit', $metricType['unit'])->first();

            if($metric == null) {
                $metric = $this->metric->create(attributes: [
                    'quantity' => $metricType['name'],
                    'unit' => $metricType['unit'],
                    'symbol' => $metricType['symbol'],
                    'created_by' => Auth::id()
                ]);
            }

            $mea = $metric->measures()->where('name', $measure['name'])->first();

            if(!$mea) {
                $metric->measures()->create(attributes: [
                    'name' => $measure['name'],
                    'unit' => $measure['unit'],
                    'symbol' => $measure['symbol'],
                    'created_by' => Auth::id()
                ]);

                DB::commit();
                return redirect()->route('unit-measures.index');
            } else {
                DB::rollBack();
                return redirect()->route('unit-measures.index');
            }
        } catch (\Throwable $th) {
                DB::rollBack();
                return redirect()->route('unit-measures.index')->with('error', 'An error occurred while creating the measure');
            }

    }

    public function show(Request $request, UnitMeasure $unitMeasure): UnitMeasureResource
    {
        return new UnitMeasureResource($unitMeasure);
    }


    public function update(Request $request, UnitMeasure $unitMeasure)
    {
        try {
            DB::beginTransaction();

            $metricType = $request->metric;
            $measure = $request->measure;

            if($measure['unit'] <= 0) {
                return back()->with('flash', [
                    'message' => "Unit must be positive number",
                    'type' => 'negativeNumber'
                ]);
            }

            $metric = $this->metric->where('unit', $metricType['unit'])->first();

            if($metric == null) {
                $metric = $this->metric->create([
                    'quantity' => $metricType['name'],
                    'unit' => $metricType['unit'],
                    'symbol' => $metricType['symbol'],
                    'description' => $metricType['description'],
                    'created_by' => Auth::id()
                ]);
            }

            // Update the existing measure
            $unitMeasure->update([
                'name' => $measure['name'],
                'unit' => $measure['unit'],
                'symbol' => $measure['symbol'],
                'description' => $measure['description'],
                'quantity_id' => $metric->id,
                'updated_by' => Auth::id()
            ]);

            DB::commit();
            return redirect()->route('unit-measures.index')->with('success', 'Unit measure updated successfully.');


        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->route('unit-measures.index')->with('error', 'An error occurred while updating the measure');
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

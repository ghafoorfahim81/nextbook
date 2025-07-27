<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\UnitMeasureStoreRequest;
use App\Http\Requests\Administration\UnitMeasureUpdateRequest;
use App\Http\Resources\Administration\UnitMeasureCollection;
use App\Http\Resources\Administration\UnitMeasureResource;
use App\Models\Administration\UnitMeasure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnitMeasureController extends Controller
{
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

    public function store(UnitMeasureStoreRequest $request): Response
    {
        $unitMeasure = UnitMeasure::create($request->validated());

        return new UnitMeasureResource($unitMeasure);
    }

    public function show(Request $request, UnitMeasure $unitMeasure): Response
    {
        return new UnitMeasureResource($unitMeasure);
    }

    public function update(UnitMeasureUpdateRequest $request, UnitMeasure $unitMeasure): Response
    {
        $unitMeasure->update($request->validated());

        return new UnitMeasureResource($unitMeasure);
    }

    public function destroy(Request $request, UnitMeasure $unitMeasure): Response
    {
        $unitMeasure->delete();

        return response()->noContent();
    }
}

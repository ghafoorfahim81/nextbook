<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\BrandStoreRequest;
use App\Http\Requests\Administration\BrandUpdateRequest;
use App\Http\Resources\Administration\BrandResource;
use App\Models\Administration\Brand;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Brand::class, 'brand');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $brands = Brand::search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Administration/Brands/Index', [
            'brands' => BrandResource::collection($brands),
        ]);
    }

    public function store(BrandStoreRequest $request)
    {
        Brand::create($request->validated());
        return redirect()->route('brands.index')->with('success', 'Brand created successfully.');
    }

    public function show(Request $request, Brand $brand)
    {
        return new BrandResource($brand);
    }

    public function update(BrandUpdateRequest $request, Brand $brand)
    {
        $brand->update($request->validated());
        return redirect()->back();
    }

    public function destroy(Request $request, Brand $brand)
    {

        // Check for dependencies before deletion
        if (!$brand->canBeDeleted()) {
            $message = $brand->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return inertia('Administration/Brands/Index', [
                'error' => $message
            ]);
        }

        $brand->delete();
        return redirect()->route('brands.index')->with('success', 'Brand deleted successfully.');
    }
    public function restore(Request $request, Brand $brand)
    {
        $brand->restore();
        return redirect()->route('brands.index')->with('success', 'Brand restored successfully.');
    }
}

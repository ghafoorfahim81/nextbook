<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\BranchStoreRequest;
use App\Http\Requests\Administration\BranchUpdateRequest;
use App\Http\Resources\Administration\BranchCollection;
use App\Http\Resources\Administration\BranchResource;
use App\Models\Administration\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'asc');

        $branches = Branch::with('parent')
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Branches/Index', [
            'items' => BranchResource::collection($branches),
        ]);
    }

    public function store(BranchStoreRequest $request)
    {
        $branch = Branch::create($request->validated());

        
    }

    public function show(Request $request, Branch $branch): Response
    {
        return new BranchResource($branch);
    }

    public function update(BranchUpdateRequest $request, Branch $branch): Response
    {
        $branch->update($request->validated());

        return new BranchResource($branch);
    }

    public function destroy(Request $request, Branch $branch): Response
    {
        $branch->delete();

        return response()->noContent();
    }
}

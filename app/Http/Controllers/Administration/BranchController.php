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
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Branch::class, 'branch');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $branches = Branch::with('parent')
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Branches/Index', [
            'branches' => BranchResource::collection($branches),
        ]);
    }

    public function store(BranchStoreRequest $request)
    {
        $branch = Branch::create($request->validated());
        return redirect()->route('branches.index')->with('success', 'Branch created successfully.');
    }

    public function show(Request $request, Branch $branch): Response
    {
        return new BranchResource($branch);
    }

    public function update(BranchUpdateRequest $request, Branch $branch)
    {
        $branch->update($request->validated());
        return redirect()->route('branches.index')->with('success', 'Branch updated successfully.');
    }

    public function destroy(Request $request, Branch $branch)
    {
        // Prevent deleting the main branch
        if ($branch->is_main) {
            return redirect()->route('branches.index')->with('error', __('You cannot delete the main branch.'));
        }

        // Check for dependencies before deletion
        if (!$branch->canBeDeleted()) {
            $message = $branch->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return redirect()->route('branches.index')->with('error', $message);
        }

        $branch->delete();
        return redirect()->route('branches.index')->with('success', __('general.branch_deleted_successfully'));
    }

    public function restore(Request $request, Branch $branch)
    {
        $branch->restore();
        return redirect()->route('branches.index')->with('success', 'Branch restored successfully.');
    }
}

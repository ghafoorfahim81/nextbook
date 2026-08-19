<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\BranchStoreRequest;
use App\Http\Requests\Administration\BranchUpdateRequest;
use App\Http\Resources\Administration\BranchResource;
use App\Models\Administration\Branch;
use App\Services\BranchProvisioningService;
use App\Support\Inertia\CacheForget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    public function __construct(
        private readonly BranchProvisioningService $provisioning,
    ) {
        $this->authorizeResource(Branch::class, 'branch');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $branches = Branch::with(['parent', 'createdBy', 'updatedBy'])
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
        // The branch and its ~200 default records land together or not at all.
        // A failure part-way through used to leave a branch that could not trade,
        // with no way to finish provisioning it from the UI.
        DB::transaction(function () use ($request) {
            $branch = Branch::create($request->validated());

            $this->provisioning->provision($branch);
        });

        $this->forgetBranchCache($request);

        return redirect()->route('branches.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.branch')]));
    }

    public function show(Request $request, Branch $branch): BranchResource
    {
        $branch->load(['parent', 'createdBy', 'updatedBy']);

        return new BranchResource($branch);
    }

    public function update(BranchUpdateRequest $request, Branch $branch)
    {
        $branch->update($request->validated());

        $this->forgetBranchCache($request);

        return redirect()->route('branches.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.branch')]));
    }

    public function destroy(Request $request, Branch $branch)
    {
        // Prevent deleting the main branch
        if ($branch->is_main) {
            return redirect()->route('branches.index')->with('error', __('general.cannot_delete_main_branch'));
        }

        // Check for dependencies before deletion
        if (!$branch->canBeDeleted()) {
            $message = $branch->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return redirect()->route('branches.index')->with('error', $message);
        }

        $branch->delete();

        $this->forgetBranchCache($request);

        return redirect()->route('branches.index')->with('success', __('general.branch_deleted_successfully'));
    }

    public function restore(Request $request, Branch $branch)
    {
        $branch->restore();

        $this->forgetBranchCache($request);

        return redirect()->route('branches.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.branch')]));
    }

    public function forceDelete(Request $request, Branch $branch)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('branches', (string) $branch->id);

        $this->forgetBranchCache($request);

        return redirect()->route('branches.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.branch')]));
    }

    /**
     * Drop the cached branch list that feeds the switcher.
     *
     * It is cached per company/branch/locale for an hour, so without this a branch
     * that was just created, renamed or deleted keeps showing the old list.
     */
    private function forgetBranchCache(Request $request): void
    {
        CacheForget::lookupEverywhere($request, 'branches');
        CacheForget::lookupEverywhere($request, 'main_branch');
    }
}

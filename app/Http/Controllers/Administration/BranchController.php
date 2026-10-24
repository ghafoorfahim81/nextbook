<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\BranchStoreRequest;
use App\Http\Requests\Administration\BranchUpdateRequest;
use App\Http\Resources\Administration\BranchResource;
use App\Models\Administration\Branch;
use App\Services\BranchProvisioningService;
use App\Support\CompanyContext;
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

    /**
     * The branch list is the workspace picker (WorkspaceController::branches),
     * the same way the companies list is: one place to switch, open, add and
     * edit branches.
     */
    public function index(Request $request)
    {
        return redirect()->route('workspace.branches');
    }

    public function create(Request $request)
    {
        return inertia('Branch/Create', [
            'parentOptions' => $this->parentOptions(),
        ]);
    }

    public function store(BranchStoreRequest $request)
    {
        // The branch and its ~200 default records land together or not at all.
        // A failure part-way through used to leave a branch that could not trade,
        // with no way to finish provisioning it from the UI.
        $branch = DB::transaction(function () use ($request) {
            // A branch belongs to the company being worked in; the form does not ask.
            $branch = Branch::create([...$request->validated(), 'company_id' => CompanyContext::companyId()]);

            $this->provisioning->provision($branch);

            return $branch;
        });

        $this->forgetBranchCache($request);

        return redirect()->route('branches.show', $branch)->with('success', __('general.created_successfully', ['resource' => __('general.resource.branch')]));
    }

    public function show(Request $request, Branch $branch)
    {
        $branch->load(['parent', 'children', 'company', 'createdBy', 'updatedBy']);
        $user = $request->user();

        return inertia('Branch/Show', [
            'branch' => [
                ...BranchResource::make($branch)->resolve(),
                'company_name' => $branch->company?->localizedName(),
                'created_at' => $branch->created_at?->toDateString(),
                'updated_at' => $branch->updated_at?->toDateString(),
                'children' => $branch->children->map(fn (Branch $child) => [
                    'id' => (string) $child->id,
                    'name' => (string) $child->name,
                    'location' => $child->location,
                ])->values(),
            ],
            'isActive' => app()->bound('active_branch_id') && (string) app('active_branch_id') === (string) $branch->id,
            'can' => [
                'update' => $user->can('update', $branch),
                'delete' => $user->can('delete', $branch) && ! $branch->is_main,
            ],
        ]);
    }

    public function edit(Request $request, Branch $branch)
    {
        $branch->load('parent');

        return inertia('Branch/Edit', [
            'branch' => BranchResource::make($branch)->resolve(),
            'parentOptions' => $this->parentOptions($branch),
        ]);
    }

    public function update(BranchUpdateRequest $request, Branch $branch)
    {
        $branch->update($request->validated());

        $this->forgetBranchCache($request);

        return redirect()->route('branches.show', $branch)->with('success', __('general.updated_successfully', ['resource' => __('general.resource.branch')]));
    }
    public function destroy(Request $request, Branch $branch)
    {
        // Prevent deleting the main branch
        if ($branch->is_main) {
            return redirect()->route('workspace.branches')->with('error', __('general.cannot_delete_main_branch'));
        }

        // Check for dependencies before deletion
        if (!$branch->canBeDeleted()) {
            $message = $branch->getDependencyMessage() ?? __('messages.dependency.cannot_delete_generic');
            return redirect()->route('workspace.branches')->with('error', $message);
        }

        $branch->delete();

        $this->forgetBranchCache($request);

        return redirect()->route('workspace.branches')->with('success', __('general.branch_deleted_successfully'));
    }

    public function restore(Request $request, Branch $branch)
    {
        $branch->restore();

        $this->forgetBranchCache($request);

        return redirect()->route('workspace.branches')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.branch')]));
    }

    public function forceDelete(Request $request, Branch $branch)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('branches', (string) $branch->id);

        $this->forgetBranchCache($request);

        return redirect()->route('workspace.branches')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.branch')]));
    }

    /**
     * The branches a branch may sit under: this company's, and never the
     * branch itself.
     *
     * @return list<array{id: string, name: string}>
     */
    private function parentOptions(?Branch $except = null): array
    {
        return Branch::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $branch) => ['id' => (string) $branch->id, 'name' => (string) $branch->name])
            ->all();
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

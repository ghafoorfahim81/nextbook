<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CategoryStoreRequest;
use App\Http\Requests\Administration\CategoryUpdateRequest;
use App\Http\Resources\Administration\CategoryCollection;
use App\Http\Resources\Administration\CategoryResource;
use App\Models\Administration\Category;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use App\Support\Inertia\CacheForget;
use App\Support\Inertia\CacheKey;
use Illuminate\Support\Facades\Cache;
class CategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Category::class, 'category');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $categories = Category::with(['parent', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Administration/Categories/Index', [
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function store(CategoryStoreRequest $request, ActivityLogService $activityLogService)
    {
        $category = Category::create($request->validated());

        $activityLogService->logCreate(
            reference: $category,
            module: 'category',
            description: "Category {$category->name} created.",
            newValues: [
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'remark' => $category->remark,
                'is_active' => $category->is_active,
                'branch_id' => $category->branch_id,
            ],
            branchId: $category->branch_id,
        );

        return redirect()->route('categories.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.category')]));
    }

    public function show(Request $request, Category $category): CategoryResource
    {
        $category->load(['parent', 'createdBy', 'updatedBy']);
        return new CategoryResource($category);
    }


    public function update(CategoryUpdateRequest $request, Category $category, ActivityLogService $activityLogService)
    {
        $beforeState = [
            'name' => $category->name,
            'parent_id' => $category->parent_id,
            'remark' => $category->remark,
            'is_active' => $category->is_active,
            'branch_id' => $category->branch_id,
        ];

        $category->update($request->validated());

        $activityLogService->logUpdate(
            reference: $category,
            before: $beforeState,
            after: [
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'remark' => $category->remark,
                'is_active' => $category->is_active,
                'branch_id' => $category->branch_id,
            ],
            module: 'category',
            description: "Category {$category->name} updated.",
            branchId: $category->branch_id,
        );

        return redirect()->back();
    }

    public function destroy(Request $request, Category $category, ActivityLogService $activityLogService)
    {
        // Check for dependencies before deletion
        if (!$category->canBeDeleted()) {
            $message = $category->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return inertia('Administration/Categories/Index', [
                'error' => $message
            ]);
        }

        $oldValues = [
            'name' => $category->name,
            'parent_id' => $category->parent_id,
            'remark' => $category->remark,
            'is_active' => $category->is_active,
            'branch_id' => $category->branch_id,
        ];

        $category->delete();

        $activityLogService->logDelete(
            reference: $category,
            module: 'category',
            description: "Category {$category->name} deleted.",
            oldValues: $oldValues,
            branchId: $category->branch_id,
        );

        return redirect()->route('categories.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.category')]));
    }

    public function restore(Request $request, Category $category, ActivityLogService $activityLogService)
    {
        $category->restore();

        $activityLogService->logAction(
            eventType: 'restored',
            reference: $category,
            module: 'category',
            description: "Category {$category->name} restored.",
            newValues: [
                'name' => $category->name,
                'is_active' => $category->is_active,
            ],
            branchId: $category->branch_id,
        );

        return back()->with('success', __('general.restored_successfully', ['resource' => __('general.resource.category')]));
    }

    public function forceDelete(Request $request, Category $category)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('categories', (string) $category->id);
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'categories'));

        return back()->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.category')]));
    }
}

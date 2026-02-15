<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CategoryStoreRequest;
use App\Http\Requests\Administration\CategoryUpdateRequest;
use App\Http\Resources\Administration\CategoryCollection;
use App\Http\Resources\Administration\CategoryResource;
use App\Models\Administration\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Category::class, 'category');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
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

    public function store(CategoryStoreRequest $request)
    {
        $category = Category::create($request->validated());
        return redirect()->route('categories.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.category')]));
    }

    public function show(Request $request, Category $category): CategoryResource
    {
        $category->load(['parent', 'createdBy', 'updatedBy']);
        return new CategoryResource($category);
    }


    public function update(CategoryUpdateRequest $request, Category $category)
    {
        $category->update($request->validated());
        return redirect()->back();
    }

    public function destroy(Request $request, Category $category)
    {
        // Check for dependencies before deletion
        if (!$category->canBeDeleted()) {
            $message = $category->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return inertia('Administration/Categories/Index', [
                'error' => $message
            ]);
        }

        $category->delete();
        return redirect()->route('categories.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.category')]));
    }

    public function restore(Request $request, Category $category)
    {
        $category->restore();
        return back()->with('success', __('general.restored_successfully', ['resource' => __('general.resource.category')]));
    }
}

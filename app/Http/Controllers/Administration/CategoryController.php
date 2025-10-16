<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CategoryStoreRequest;
use App\Http\Requests\Administration\CategoryUpdateRequest;
use App\Http\Resources\Administration\CategoryCollection;
use App\Http\Resources\Administration\CategoryResource;
use App\Models\Administration\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $categories = Category::with('parent')
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
        return redirect()->route('categories.index')->with('success', 'Category created successfully.');
    }

    public function show(Request $request, Category $category): Response
    {
        $category->load('parent');
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
            return back()->withErrors(['category' => $message]);
        }

        $category->delete();
        return back()->with('success', 'Category deleted successfully.');
    }

    public function restore(Request $request, Category $category)
    {
        $category->restore();
        return back()->with('success', 'Category restored successfully.');
    }
}

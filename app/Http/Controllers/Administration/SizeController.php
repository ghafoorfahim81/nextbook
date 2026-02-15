<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\SizeStoreRequest;
use App\Http\Requests\Administration\SizeUpdateRequest;
use App\Http\Resources\Administration\SizeResource;
use App\Models\Administration\Size;
use Illuminate\Http\Request;

class SizeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Size::class, 'size');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $sizes = Size::search($request->query('search'))
            ->where('is_active', true)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Administration/Sizes/Index', [
            'sizes' => SizeResource::collection($sizes),
        ]);
    }

    public function store(SizeStoreRequest $request)
    {
        Size::create($request->validated());
        return redirect()->route('sizes.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.size')]));
    }

    public function show(Request $request, Size $size)
    {
        return new SizeResource($size);
    }

    public function update(SizeUpdateRequest $request, Size $size)
    {
        $size->update($request->validated());
        return redirect()->back();
    }

    public function destroy(Request $request, Size $size)
    {

        // Check for dependencies before deletion
        if (!$size->canBeDeleted()) {
            $message = $size->getDependencyMessage() ?? 'You cannot delete this record because it has dependencies.';
            return inertia('Administration/Sizes/Index', [
                'error' => $message
            ]);
        }

        $size->delete();
        return redirect()->route('sizes.index')->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.size')]));
    }

    public function restore(Request $request, Size $size)
    {
        $size->restore();
        return redirect()->route('sizes.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.size')]));
    }
}

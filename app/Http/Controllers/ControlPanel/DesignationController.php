<?php

namespace App\Http\Controllers\ControlPanel;

use App\Http\Controllers\Controller;
use App\Http\Requests\ControlPanel\DesignationStoreRequest;
use App\Http\Requests\ControlPanel\DesignationUpdateRequest;
use App\Http\Resources\ControlPanel\DesignationCollection;
use App\Http\Resources\ControlPanel\DesignationResource;
use App\Models\ControlPanel\Designation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
class DesignationController extends Controller
{


    // public function index(Request $request)
    // {
    //     $search = $request->input('search');

    //     $designations = Designation::query()
    //         ->when($search, function ($query, $search) {
    //             return $query->where('name', 'like', "%{$search}%")
    //                          ->orWhere('remark', 'like', "%{$search}%");
    //         })
    //         ->paginate(10);

    //     return inertia('Designations/Index', [
    //         'data' => $designations->items(), // Make sure `data` is correct
    //         'pagination' => [
    //             'current_page' => $designations->currentPage(),
    //             'last_page' => $designations->lastPage(),
    //             'total' => $designations->total(),
    //         ],
    //     ]);
    // }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 10); // Default to 10 per page

        $designations = Designation::query()
            ->when($search, function ($query, $search) {
                return $query->where('name', 'like', "%{$search}%")
                            ->orWhere('remark', 'like', "%{$search}%");
            })
            ->paginate($perPage);

        return inertia('Designations/Index', [
            'data' => $designations->items(), // Table data
            'pagination' => [
                'current_page' => $designations->currentPage(),
                'last_page' => $designations->lastPage(),
                'total' => $designations->total(),
            ],
        ]);
    }





    // public function index(Request $request)
    // {
    //     $search = $request->input('search');

    //     $designations = Designation::query()
    //         ->when($search, function ($query, $search) {
    //             return $query->where('name', 'like', "%{$search}%")
    //                          ->orWhere('remark', 'like', "%{$search}%");
    //         })
    //         ->paginate(10); // Adjust pagination as needed

    //     return response()->json([
    //         'data' => $designations->items(),
    //         'pagination' => [
    //             'current_page' => $designations->currentPage(),
    //             'last_page' => $designations->lastPage(),
    //             'total' => $designations->total(),
    //         ],
    //     ]);
    // }


    public function store(DesignationStoreRequest $request): DesignationResource
    {
        $designation = Designation::create($request->validated());

        return new DesignationResource($designation);
    }

    public function show(Request $request, Designation $designation): DesignationResource
    {
        return new DesignationResource($designation);
    }

    public function update(DesignationUpdateRequest $request, Designation $designation): DesignationResource
    {
        $designation->update($request->validated());

        return new DesignationResource($designation);
    }

    public function destroy(Request $request, Designation $designation): DesignationResource
    {
        $designation->delete();

        return response()->noContent();
    }
}

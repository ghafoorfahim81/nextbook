<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CustomerGroupRequest;
use App\Models\Administration\CustomerGroup;
use Illuminate\Http\Request;

class CustomerGroupController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return inertia('Administration/CustomerGroups/Index', [
            'customerGroups' => CustomerGroup::query()
                ->search($request->query('search'))
                ->orderBy('name_en')
                ->paginate($request->integer('perPage', recordsPerPage()))
                ->withQueryString(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CustomerGroupRequest $request)
    {
        CustomerGroup::create($request->validated());
        return back()->with('success', __('general.created_successfully'));
    }

    /**
     * Display the specified resource.
     */
    public function show(CustomerGroup $customerGroup)
    {
        return response()->json($customerGroup);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CustomerGroupRequest $request, CustomerGroup $customerGroup)
    {
        $customerGroup->update($request->validated());
        return back()->with('success', __('general.updated_successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CustomerGroup $customerGroup)
    {
        $customerGroup->delete();
        return back()->with('success', __('general.deleted_successfully'));
    }
}

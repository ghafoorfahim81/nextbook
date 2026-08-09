<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\PaymentTermRequest;
use App\Models\Administration\PaymentTerm;
use Illuminate\Http\Request;

class PaymentTermController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return inertia('Administration/PaymentTerms/Index', [
            'paymentTerms' => PaymentTerm::query()
                ->search($request->query('search'))
                ->orderBy('name')
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
    public function store(PaymentTermRequest $request)
    {
        PaymentTerm::create($request->validated());
        return back()->with('success', __('general.created_successfully'));
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentTerm $paymentTerm)
    {
        return response()->json($paymentTerm);
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
    public function update(PaymentTermRequest $request, PaymentTerm $paymentTerm)
    {
        $paymentTerm->update($request->validated());
        return back()->with('success', __('general.updated_successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentTerm $paymentTerm)
    {
        $paymentTerm->delete();
        return back()->with('success', __('general.deleted_successfully'));
    }
}

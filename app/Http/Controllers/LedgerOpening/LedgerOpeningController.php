<?php

namespace App\Http\Controllers\LedgerOpening;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerOpeningStoreRequest;
use App\Http\Requests\Ledger\LedgerOpeningUpdateRequest;
use App\Http\Resources\Ledger\LedgerOpeningCollection;
use App\Http\Resources\Ledger\LedgerOpeningResource;
use App\Models\Ledger\LedgerOpening;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LedgerOpeningController extends Controller
{
    public function index(Request $request): Response
    {
        $ledgerOpenings = LedgerOpening::all();

        return new LedgerOpeningCollection($ledgerOpenings);
    }

    public function store(LedgerOpeningStoreRequest $request): Response
    {
        $ledgerOpening = LedgerOpening::create($request->validated());

        return new LedgerOpeningResource($ledgerOpening);
    }

    public function show(Request $request, LedgerOpening $ledgerOpening): Response
    {
        return new LedgerOpeningResource($ledgerOpening);
    }

    public function update(LedgerOpeningUpdateRequest $request, LedgerOpening $ledgerOpening): Response
    {
        $ledgerOpening->update($request->validated());

        return new LedgerOpeningResource($ledgerOpening);
    }

    public function destroy(Request $request, LedgerOpening $ledgerOpening): Response
    {
        $ledgerOpening->delete();

        return response()->noContent();
    }
}

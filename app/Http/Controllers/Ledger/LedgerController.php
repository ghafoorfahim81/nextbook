<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Requests\Ledger\LedgerUpdateRequest;
use App\Http\Resources\Ledger\LedgerCollection;
use App\Http\Resources\Ledger\LedgerResource;
use App\Models\Ledger\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LedgerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Ledger::class, 'ledger');
    }

    public function index(Request $request): Response
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $type = $request->input('type', 'customer'); // default to customer

        $ledgers = Ledger::search($request->query('search'))
            ->where('type', $type) // ✅ filter here
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Ledgers/Customers/Index', [
            'ledgers' => LedgerResource::collection($ledgers),
        ]);
    }


    public function store(LedgerStoreRequest $request): Response
    {
        $ledger = Ledger::create($request->validated());

        return new LedgerResource($ledger);
    }

    public function show(Request $request, Ledger $ledger): Response
    {
        return new LedgerResource($ledger);
    }

    public function update(LedgerUpdateRequest $request, Ledger $ledger): Response
    {
        $ledger->update($request->validated());

        return new LedgerResource($ledger);
    }

    public function destroy(Request $request, Ledger $ledger): Response
    {
        $ledger->delete();

        return response()->noContent();
    }
    public function restore(Request $request, Ledger $ledger)
    {
        $ledger->restore();
        return redirect()->route('ledgers.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.ledger')]));
    }
}

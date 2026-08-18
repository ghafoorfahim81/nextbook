<?php

namespace App\Http\Controllers\Ledger;

use App\Enums\LedgerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\LedgerStoreRequest;
use App\Http\Requests\Ledger\LedgerUpdateRequest;
use App\Http\Resources\Ledger\LedgerCollection;
use App\Http\Resources\Ledger\LedgerResource;
use App\Models\Ledger\Ledger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Response as InertiaResponse;

class LedgerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Ledger::class, 'ledger');
    }

    public function index(Request $request): InertiaResponse
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        // Whitelisted, not passed through: this screen is the commercial party
        // list, and `ledgers.view_any` is not authority to read staff records.
        // Taking the parameter verbatim would have let ?type=employee expose
        // every payroll ledger to anyone who can view customers.
        $type = $request->input('type');

        if (! in_array($type, LedgerType::commercialValues(), true)) {
            $type = LedgerType::CUSTOMER->value;
        }

        $ledgers = Ledger::search($request->query('search'))
            ->where('type', $type)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Ledgers/Customers/Index', [
            'ledgers' => LedgerResource::collection($ledgers),
        ]);
    }


    public function store(LedgerStoreRequest $request): LedgerResource
    {
        $ledger = Ledger::create($request->validated());

        return new LedgerResource($ledger);
    }

    public function show(Request $request, Ledger $ledger): LedgerResource
    {
        return new LedgerResource($ledger);
    }

    public function update(LedgerUpdateRequest $request, Ledger $ledger): LedgerResource
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

    public function forceDelete(Request $request, Ledger $ledger)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('ledgers', (string) $ledger->id);

        return redirect()->route('ledgers.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.ledger')]));
    }
}

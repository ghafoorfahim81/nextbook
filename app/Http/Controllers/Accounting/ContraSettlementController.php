<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\ContraSettlementStoreRequest;
use App\Http\Resources\Accounting\ContraSettlementResource;
use App\Models\Accounting\ContraSettlement;
use App\Models\Accounting\Settlement;
use App\Models\Administration\Currency;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Accounting\ContraSettlementService;
use App\Services\Accounting\PaymentStatusService;
use App\Services\Accounting\SettlementService;
use App\Services\DateConversionService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Set-offs: cancelling what a party owes us against what we owe them.
 *
 * Someone who both buys from us and sells to us has two ledgers, because a
 * ledger's control account follows its type and one balance cannot sit on both
 * the receivable and the payable side. This module is the bridge — it relieves
 * invoices on one ledger and bills on the other by the same amount, and no
 * cash moves.
 *
 * There is deliberately no edit. A posted set-off touched two parties'
 * documents; editing it would mean unwinding both sets of allocations and
 * re-applying them, and the honest way to change a set-off both parties
 * already agreed to is to reverse it and enter the right one.
 */
class ContraSettlementController extends Controller
{
    public function __construct(private readonly DateConversionService $dates)
    {
        $this->authorizeResource(ContraSettlement::class, 'contra_settlement');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $settlements = ContraSettlement::with(['customerLedger', 'supplierLedger', 'currency', 'createdBy', 'updatedBy'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('ContraSettlements/Index', [
            'contraSettlements' => ContraSettlementResource::collection($settlements),
            'filterOptions' => [
                'customers' => Ledger::query()->where('type', 'customer')->orderBy('name')->get(['id', 'name']),
                'suppliers' => Ledger::query()->where('type', 'supplier')->orderBy('name')->get(['id', 'name']),
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name', 'local_name']),
                'users' => User::query()->whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'filters' => $filters,
            ],
        ]);
    }

    public function create(Request $request)
    {
        return inertia('ContraSettlements/Create', [
            'latestNumber' => ContraSettlement::nextNumber(),
        ]);
    }

    public function store(
        ContraSettlementStoreRequest $request,
        ContraSettlementService $offsets,
        ActivityLogService $activityLog,
    ) {
        $validated = $request->validated();
        $validated['date'] = $this->dates->toGregorian($validated['date']);

        $customer = Ledger::findOrFail($validated['customer_ledger_id']);
        $supplier = Ledger::findOrFail($validated['supplier_ledger_id']);

        DB::transaction(function () use ($validated, $customer, $supplier, $offsets, $activityLog) {
            // The document exists first so both vouchers can point back at it.
            // If the offset is refused — one side cannot cover it, the parties
            // are the wrong types — the whole transaction rolls back and this
            // row goes with it.
            $document = ContraSettlement::create([
                'number' => $validated['number'],
                'date' => $validated['date'],
                'customer_ledger_id' => $customer->id,
                'supplier_ledger_id' => $supplier->id,
                'currency_id' => $validated['currency_id'],
                'rate' => $validated['rate'],
                'amount' => $validated['amount'],
                'narration' => $validated['narration'] ?? null,
                'status' => TransactionStatus::POSTED->value,
            ]);

            $posted = $offsets->offset(
                customer: $customer,
                supplier: $supplier,
                voucher: [
                    'date' => $validated['date'],
                    'branch_id' => $document->branch_id,
                    'currency_id' => $validated['currency_id'],
                    'rate' => $validated['rate'],
                    'amount' => $validated['amount'],
                    'voucher_number' => (string) $validated['number'],
                    'reference_type' => ContraSettlement::class,
                    'reference_id' => $document->id,
                    'remark' => $validated['narration'] ?? null,
                ],
                customerAllocations: $validated['customer_allocations'] ?? [],
                supplierAllocations: $validated['supplier_allocations'] ?? [],
            );

            $document->update([
                'customer_transaction_id' => $posted['customer']->id,
                'supplier_transaction_id' => $posted['supplier']->id,
            ]);

            $this->refreshSettledDocuments($document);

            $activityLog->logCreate(
                reference: $document,
                module: 'contra_settlement',
                description: "Set-off #{$document->number} posted.",
                newValues: [
                    'number' => $document->number,
                    'date' => $document->date?->toDateString(),
                    'customer_name' => $customer->name,
                    'supplier_name' => $supplier->name,
                    'amount' => (float) $document->amount,
                    'currency_id' => $document->currency_id,
                    'rate' => (float) $document->rate,
                ],
                metadata: [
                    'action' => 'contra_settlement_store',
                    'customer_transaction_id' => $posted['customer']->id,
                    'supplier_transaction_id' => $posted['supplier']->id,
                ],
            );
        });

        return redirect()
            ->route('contra-settlements.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.contra_settlement')]));
    }

    public function show(ContraSettlement $contraSettlement)
    {
        $contraSettlement->load([
            'customerLedger',
            'supplierLedger',
            'currency',
            'createdBy',
            'updatedBy',
        ]);

        $settlements = app(SettlementService::class);

        return inertia('ContraSettlements/Show', [
            'contraSettlement' => new ContraSettlementResource($contraSettlement),
            // Named documents on each side, not raw line ids — the settlement
            // rows alone cannot say which invoice or bill they relieved.
            'customerSettlements' => $contraSettlement->customer_transaction_id
                ? $settlements->settlementsForVoucher($contraSettlement->customer_transaction_id)
                : [],
            'supplierSettlements' => $contraSettlement->supplier_transaction_id
                ? $settlements->settlementsForVoucher($contraSettlement->supplier_transaction_id)
                : [],
        ]);
    }

    public function reverse(
        Request $request,
        ContraSettlement $contraSettlement,
        TransactionService $transactions,
        ActivityLogService $activityLog,
    ) {
        $this->authorize('update', $contraSettlement);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($contraSettlement->status === TransactionStatus::REVERSED->value) {
            abort(422, 'This set-off has already been reversed.');
        }

        DB::transaction(function () use ($contraSettlement, $transactions, $validated) {
            $statuses = app(PaymentStatusService::class);

            // Read the documents BEFORE the settlements go, or there is nothing
            // left to look them up by.
            $affected = ['sales' => [], 'purchases' => []];

            foreach ($contraSettlement->transactionIds() as $transactionId) {
                $settled = $statuses->documentsSettledBy($transactionId);
                $affected['sales'] = array_merge($affected['sales'], $settled['sales']);
                $affected['purchases'] = array_merge($affected['purchases'], $settled['purchases']);
            }

            // Both halves, or neither. Reversing one side alone would strand a
            // balance on the clearing account and leave one party relieved of a
            // debt the other still carries.
            foreach ($contraSettlement->transactionIds() as $transactionId) {
                $transaction = Transaction::findOrFail($transactionId);

                $transactions->reverse(
                    $transaction,
                    $validated['reason'] ?? null,
                    $contraSettlement->number,
                    ContraSettlement::class,
                );

                // Soft-deleted, not force-deleted: a reversal keeps its trail.
                Settlement::withoutGlobalScopes()
                    ->where('transaction_id', $transactionId)
                    ->delete();
            }

            $statuses->recalculateSales(array_values(array_unique($affected['sales'])));
            $statuses->recalculatePurchases(array_values(array_unique($affected['purchases'])));

            $contraSettlement->update([
                'status' => TransactionStatus::REVERSED->value,
                'updated_by' => Auth::id(),
            ]);
        });

        $activityLog->logAction(
            eventType: 'reversed',
            reference: $contraSettlement,
            module: 'contra_settlement',
            description: "Set-off #{$contraSettlement->number} reversed.",
            metadata: [
                'action' => 'contra_settlement_reverse',
                'reason' => $validated['reason'] ?? null,
            ],
        );

        return back()->with(
            'success',
            __('general.updated_successfully', ['resource' => __('general.resource.contra_settlement')])
        );
    }

    public function destroy(ContraSettlement $contraSettlement, ActivityLogService $activityLog)
    {
        $oldValues = [
            'number' => $contraSettlement->number,
            'date' => $contraSettlement->date?->toDateString(),
            'customer_name' => $contraSettlement->customerLedger?->name,
            'supplier_name' => $contraSettlement->supplierLedger?->name,
            'amount' => (float) $contraSettlement->amount,
        ];

        DB::transaction(function () use ($contraSettlement) {
            $statuses = app(PaymentStatusService::class);
            $affected = ['sales' => [], 'purchases' => []];

            foreach ($contraSettlement->transactionIds() as $transactionId) {
                $settled = $statuses->documentsSettledBy($transactionId);
                $affected['sales'] = array_merge($affected['sales'], $settled['sales']);
                $affected['purchases'] = array_merge($affected['purchases'], $settled['purchases']);

                Settlement::withoutGlobalScopes()->where('transaction_id', $transactionId)->delete();

                $transaction = Transaction::find($transactionId);
                $transaction?->lines()->delete();
                $transaction?->delete();
            }

            $contraSettlement->delete();

            $statuses->recalculateSales(array_values(array_unique($affected['sales'])));
            $statuses->recalculatePurchases(array_values(array_unique($affected['purchases'])));
        });

        $activityLog->logDelete(
            reference: $contraSettlement,
            module: 'contra_settlement',
            description: "Set-off #{$contraSettlement->number} deleted.",
            oldValues: $oldValues,
            metadata: ['action' => 'contra_settlement_delete'],
        );

        return redirect()
            ->route('contra-settlements.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.contra_settlement')]));
    }

    public function restore(ContraSettlement $contraSettlement)
    {
        $this->authorize('update', $contraSettlement);

        app(\App\Services\DeletedRecordService::class)->restore('contra_settlements', (string) $contraSettlement->id);

        return redirect()
            ->route('contra-settlements.index')
            ->with('success', __('general.restored_successfully', ['resource' => __('general.resource.contra_settlement')]));
    }

    public function forceDelete(ContraSettlement $contraSettlement)
    {
        $this->authorize('delete', $contraSettlement);

        app(\App\Services\DeletedRecordService::class)->forceDelete('contra_settlements', (string) $contraSettlement->id);

        return redirect()
            ->route('contra-settlements.index')
            ->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.contra_settlement')]));
    }

    /**
     * Re-derive the paid/partly-paid badge on whatever the two halves relieved.
     *
     * Driven off the settlement rows rather than the lists the form sent, so it
     * stays right when an allocation was split across rates or dropped.
     */
    private function refreshSettledDocuments(ContraSettlement $document): void
    {
        $statuses = app(PaymentStatusService::class);
        $sales = [];
        $purchases = [];

        foreach ($document->transactionIds() as $transactionId) {
            $settled = $statuses->documentsSettledBy($transactionId);
            $sales = array_merge($sales, $settled['sales']);
            $purchases = array_merge($purchases, $settled['purchases']);
        }

        $statuses->recalculateSales(array_values(array_unique($sales)));
        $statuses->recalculatePurchases(array_values(array_unique($purchases)));
    }
}

<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseStoreRequest;
use App\Http\Requests\Purchase\PurchaseUpdateRequest;
use App\Http\Resources\Purchase\PurchaseResource;
use App\Models\Purchase\Purchase;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Models\Administration\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TransactionService;
use App\Models\Account\Account;
use App\Services\StockService;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use Illuminate\Support\Facades\Cache;
class PurchaseController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Purchase::class, 'purchase');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $purchases = Purchase::with(['supplier', 'transaction.currency', 'stocks.warehouse'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Purchase/Purchases/Index', [
            'purchases' => PurchaseResource::collection($purchases),
            'filterOptions' => [
                'suppliers' => Ledger::query()->where('type', 'supplier')->orderBy('name')->get(['id', 'name']),
                'currencies' => Currency::orderBy('code')->get(['id', 'code', 'name']),
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
                'types' => [
                    ['id' => 'cash', 'name' => 'Cash'],
                    ['id' => 'credit', 'name' => 'Credit'],
                ],
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
        $purchaseNumber = Purchase::max('number') ? Purchase::max('number') + 1 : 1;
        $bankAccounts = new Account();
        $bankAccounts = $bankAccounts->getAccountsByAccountTypeSlug('cash-or-bank');
        return inertia('Purchase/Purchases/Create', [
            'purchaseNumber' => $purchaseNumber,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function store(PurchaseStoreRequest $request, TransactionService $transactionService, StockService $stockService)
    {
        //dd($request->all());
        $validated = $request->validated();
        $purchase = DB::transaction(function () use ($request, $transactionService, $stockService) {
            // Create purchase
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();

            $validated['type']  = $validated['purchase_type'] ?? 'cash';
            $validated['status'] = TransactionStatus::POSTED->value;

            // Convert date properly
            $validated['date'] = $dateConversionService->toGregorian($validated['date']);

            $purchase = Purchase::create($validated);
            $validated['item_list'] = array_map(function ($item) use ($dateConversionService, $validated) {
                $item['expire_date'] = $item['expire_date'] ? $dateConversionService->toGregorian($item['expire_date']) : null;
                $item['discount'] = $item['item_discount'] ? $item['item_discount'] : 0;
                $item['warehouse_id'] = $validated['warehouse_id'];
                return $item;
            }, $validated['item_list']);
            $purchase->items()->createMany($validated['item_list']);

            $lines = [];
            foreach ($validated['item_list'] as $item) {
                $stock = $stockService->post([
                    'item_id'         => $item['item_id'],
                    'movement_type'   => StockMovementType::IN->value,
                    'unit_measure_id' => $item['unit_measure_id'], // from item form
                    'quantity'        => (float) $item['quantity'],
                    'source'          => StockSourceType::PURCHASE->value,
                    'unit_cost'       => (float) $item['unit_price'],
                    'status'          => StockStatus::DRAFT->value,
                    'batch'           => $item['batch'] ?? null,
                    'date'            => $validated['date'],
                    'expire_date'     => $item['expire_date'],
                    'size_id'         => $validated['size_id'] ?? null,
                    'warehouse_id'    => $validated['warehouse_id'],
                    'branch_id'       => $purchase->branch_id,
                    'reference_type'  => Purchase::class,
                    'reference_id'    => $purchase->id,
                ]);
                $itemModel = \App\Models\Inventory\Item::find($item['item_id']);
                $accountId = $itemModel->asset_account_id ?? $itemModel->cost_account_id;
                $lines[] = [
                    'account_id' => $accountId,
                    'ledger_id'  => null,
                    'debit'      => (float)$item['quantity'] * (float)$item['unit_price'],
                    'credit'     => 0,
                    'remark'     => 'Purchase item: '.$itemModel->name,
                ];

                // $stockService->addStock($item, $validated['warehouse_id'], Purchase::class, $purchase->id, $validated['date']);
            }
            if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {

                $lines[] = [
                    'account_id' => $validated['bank_account_id'], // cash/bank
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Payment for purchase #' . $purchase->number,
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
                $glAccounts = Cache::get('gl_accounts');
                $lines[] = [
                    'account_id' => $glAccounts['account-payable'], // cash/bank
                    'ledger_id'  => $validated['supplier_id'],
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Payment for purchase #' . $purchase->number,
                ];
            }

            $transactionService->post(
                header: [
                    'currency_id'   => $validated['currency_id'],
                    'rate'          => $validated['rate'],
                    'date'          => $validated['date'],
                    'remark'        => 'Purchase #' . $purchase->number,
                    'status'        => TransactionStatus::POSTED->value,
                    'reference_type'=> Purchase::class,
                    'reference_id'  => $purchase->id,
                ],
                lines: $lines
            );


            // Create accounting transactions


            return $purchase;
        });

        if ((bool) $request->create_and_new) {
            // Stay on the same page; frontend will reset form and increment number
            return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.purchase')]));
        }

        return redirect()->route('purchases.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.purchase')]));
    }


    public function show(Request $request, Purchase $purchase)
    {
        $purchase->load(['items.item', 'items.unitMeasure', 'supplier', 'transaction.currency']);

        return response()->json([
            'data' => new PurchaseResource($purchase),
        ]);
    }

    public function edit(Request $request, Purchase $purchase)
    {
        return inertia('Purchase/Purchases/Edit', [
            'purchase' => new PurchaseResource($purchase->load(['items', 'supplier', 'transaction', 'stocks'])),
        ]);
    }

    public function update(PurchaseUpdateRequest $request, Purchase $purchase, TransactionService $transactionService, StockService $stockService)
    {

        $purchase = DB::transaction(function () use ($request, $purchase, $transactionService, $stockService) {
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();
            $stockService = new StockService();
            // Convert date properly
            if (isset($validated['date'])) {
                $validated['date'] = $dateConversionService->toGregorian($validated['date']);
            }

            $validated['type'] = $validated['sale_purchase_type_id'] ?? $purchase->type;

            // Update main purchase record
            $purchase->update($validated);

            // Handle items if they are being updated
            if (isset($validated['item_list'])) {
                // Remove old stock entries
                // Delete old items and create new ones
                $purchase->items()->forceDelete();
                $validated['item_list'] = array_map(function ($item) use ($dateConversionService, $validated) {
                    $item['expire_date'] = $item['expire_date'] ? $dateConversionService->toGregorian($item['expire_date']) : null;
                    $item['discount'] = $item['discount'] ? $item['discount'] : 0;
                    $item['warehouse_id'] = $validated['warehouse_id'];
                    return $item;
                }, $validated['item_list']);
                $purchase->items()->createMany($validated['item_list']);
                $stockService->updateStock($validated['item_list'], $validated['warehouse_id'], Purchase::class, $purchase->id, $validated['date']);

            }

            $purchase->transaction()->forceDelete();
            // Update transactions if payment details changed
            $transactions = $transactionService->createPurchaseTransactions(
                $purchase,
                \App\Models\Ledger\Ledger::find($validated['supplier_id']),
                $validated['transaction_total'],
                $validated['sale_purchase_type_id'] ?? 'cash',
                $validated['payment'] ?? [],
                $validated['currency_id'] ?? null,
                $validated['rate'] ?? null,
            );

            return $purchase;
        });

        return redirect()->route('purchases.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.purchase')]));
    }

    public function destroy(Request $request, Purchase $purchase)
    {

        $purchase->items()->delete();
        $purchase->stocks()->delete();
        $purchase->transaction()->delete();
        $purchase->delete();
        return redirect()->route('purchases.index')->with('success', __('general.purchase_deleted_successfully'));

    }
    public function restore(Request $request, Purchase $purchase)
    {

        $purchase->restore();
        $purchase->items()->restore();
        $purchase->stocks()->restore();
        $purchase->transaction()->restore();
        return redirect()->route('purchases.index')->with('success', __('general.purchase_restored_successfully'));
    }

    public function updatePurchaseStatus(Request $request, Purchase $purchase)
    {
        $purchase->update(['status' => $request->status]);
        return back()->with('success', __('general.purchase_status_updated_successfully'));
    }
}

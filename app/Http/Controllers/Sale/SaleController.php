<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SaleStoreRequest;
use App\Http\Requests\Sale\SaleUpdateRequest;
use App\Http\Resources\Sale\SaleResource;
use App\Models\Sale\Sale;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Models\Administration\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TransactionService;
use App\Models\Account\Account;
use App\Services\StockService;
use App\Models\Transaction\Transaction;
use Mpdf\Mpdf;
use App\Enums\TransactionStatus;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use Illuminate\Support\Facades\Cache;
class SaleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Sale::class, 'sale');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');
        $filters = (array) $request->input('filters', []);

        $sales = Sale::with(['customer', 'transaction.currency', 'stockOuts.warehouse'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Sale/Sales/Index', [
            'sales' => SaleResource::collection($sales),
            'filterOptions' => [
                'customers' => Ledger::query()->where('type', 'customer')->orderBy('name')->get(['id', 'name']),
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
        $saleNumber = Sale::max('number') ? Sale::max('number') + 1 : 1;
        $bankAccounts = new Account();
        $bankAccounts = $bankAccounts->getAccountsByAccountTypeSlug('cash-or-bank');

        return inertia('Sale/Sales/Create', [
            'saleNumber' => $saleNumber,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function store(SaleStoreRequest $request, TransactionService $transactionService, StockService $stockService)
    {
        $validated = $request->validated();
        $sale = DB::transaction(function () use ($request, $transactionService, $stockService) {
            // Create purchase
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();

            $validated['type']  = $validated['purchase_type'] ?? 'cash';
            $validated['status'] = TransactionStatus::POSTED->value;

            // Convert date properly
            $validated['date'] = $dateConversionService->toGregorian($validated['date']);

            $sale = Sale::create($validated);
            $validated['item_list'] = array_map(function ($item) use ($dateConversionService, $validated) {
                $item['expire_date'] = $item['expire_date'] ? $dateConversionService->toGregorian($item['expire_date']) : null;
                $item['discount'] = $item['item_discount'] ? $item['item_discount'] : 0;
                $item['warehouse_id'] = $validated['warehouse_id'];
                return $item;
            }, $validated['item_list']);
            $sale->items()->createMany($validated['item_list']);

            $lines = [];
            foreach ($validated['item_list'] as $item) {
                $stock = $stockService->post([
                    'item_id'         => $item['item_id'],
                    'movement_type'   => StockMovementType::OUT->value,
                    'unit_measure_id' => $item['unit_measure_id'], // from item form
                    'quantity'        => (float) $item['quantity'],
                    'source'          => StockSourceType::SALE->value,
                    'unit_cost'       => (float) $item['unit_price'],
                    'status'          => StockStatus::POSTED->value,
                    'batch'           => $item['batch'] ?? null,
                    'date'            => $validated['date'],
                    'expire_date'     => $item['expire_date'],
                    'size_id'         => $validated['size_id'] ?? null,
                    'warehouse_id'    => $validated['warehouse_id'],
                    'branch_id'       => $sale->branch_id,
                    'reference_type'  => Sale::class,
                    'reference_id'    => $sale->id,
                ]);
                $itemModel = \App\Models\Inventory\Item::find($item['item_id']);
                $accountId = $itemModel->asset_account_id ?? $itemModel->cost_account_id;
                $lines[] = [
                    'account_id' => $accountId,
                    'ledger_id'  => null,
                    'debit'      => (float)$item['quantity'] * (float)$item['unit_price'],
                    'credit'     => 0,
                    'remark'     => 'Sale item: '.$itemModel->name,
                ];

                // $stockService->addStock($item, $validated['warehouse_id'], Sale::class, $sale->id, $validated['date']);
            }
            if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {

                $lines[] = [
                    'account_id' => $validated['bank_account_id'], // cash/bank
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                        'remark'     => 'Payment for sale #' . $sale->number,
                ];
            }
            $glAccounts = Cache::get('gl_accounts');
            if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
                $lines[] = [
                    'account_id' => $glAccounts['account-payable'], // cash/bank
                    'ledger_id'  => $validated['supplier_id'],
                    'debit'      => 0,
                    'credit'     => $validated['transaction_total'],
                    'remark'     => 'Payment for sale #' . $sale->number,
                ];
            }
            if($validated['type'] === \App\Enums\SalePurchaseType::Credit->value) {
                if($validated['payment']['amount'] > 0) {
                    $amount = (float) $validated['payment']['amount'];
                    $lines[] = [
                        'account_id' => $validated['payment']['account_id'],
                        'debit' => 0,
                        'credit' => $amount,
                    ];
                    $lines[] = [
                        'account_id' => $glAccounts['account-payable'],
                        'ledger_id' => $validated['supplier_id'],
                        'debit' => 0,
                        'credit' => $validated['transaction_total'] - $amount,
                        'remark' => 'Payment for sale #' . $sale->number,
                    ];
                }
                else{
                    $lines[] = [
                        'account_id' => $glAccounts['account-payable'],
                        'ledger_id' => $validated['supplier_id'],
                        'debit' => 0,
                        'credit' => $validated['transaction_total'],
                        'remark' => 'Payment for sale #' . $sale->number,
                    ];
                }
            }

            $transactionService->post(
                header: [
                    'currency_id'   => $validated['currency_id'],
                    'rate'          => $validated['rate'],
                    'date'          => $validated['date'],
                    'remark'        => 'Sale #' . $sale->number,
                    'status'        => TransactionStatus::POSTED->value,
                    'reference_type'=> Sale::class,
                    'reference_id'  => $sale->id,
                ],
                lines: $lines
            );


            // Create accounting transactions


            return $sale;
        });

        if ((bool) $request->create_and_new) {
            // Stay on the same page; frontend will reset form and increment number
            return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.sale')]));
        }

        return redirect()->route('sales.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.sale')]));
    }

    public function show(Request $request, Sale $sale)
    {
        $sale->load(['items.item', 'items.unitMeasure', 'customer', 'transaction.currency']);

        return response()->json([
            'data' => new SaleResource($sale),
        ]);
    }

    public function edit(Request $request, Sale $sale)
    {
        return inertia('Sale/Sales/Edit', [
            'sale' => new SaleResource($sale->load(['items', 'customer', 'transaction', 'stockOuts'])),
        ]);
    }

    public function update(SaleUpdateRequest $request, Sale $sale, TransactionService $transactionService, StockService $stockService)
    {
        $sale = DB::transaction(function () use ($request, $sale, $transactionService, $stockService) {
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();

            // Convert date properly
            if (isset($validated['date'])) {
                $validated['date'] = $dateConversionService->toGregorian($validated['date']);
            }

            $validated['type'] = $validated['sale_purchase_type_id'] ?? $sale->type;

            // Update main sale record
            $sale->update($validated);

            // Handle items if they are being updated
            if (isset($validated['item_list'])) {
                // Remove old stock out entries
                $sale->items()->forceDelete();
                $validated['item_list'] = array_map(function ($item) use ($dateConversionService, $validated) {
                    $item['expire_date'] = $item['expire_date'] ? $dateConversionService->toGregorian($item['expire_date']) : null;
                    $item['discount'] = $item['discount'] ? $item['discount'] : 0;
                    $item['warehouse_id'] = $validated['warehouse_id'];
                    return $item;
                }, $validated['item_list']);
                $sale->items()->createMany($validated['item_list']);
                $sale->stockOuts()->forceDelete();
                // Add new stock out entries
                foreach ($validated['item_list'] as $item) {
                    $stockService->removeStock($item, $validated['warehouse_id'], 'sale', $sale->id);
                }
            }

            $sale->transaction()->forceDelete();
            // Update transactions if payment details changed
            $transactions = $transactionService->createSaleTransactions(
                $sale,
                \App\Models\Ledger\Ledger::find($validated['customer_id']),
                $validated['transaction_total'],
                $validated['sale_purchase_type_id'] ?? 'cash',
                $validated['payment'] ?? [],
                $validated['currency_id'] ?? null,
                $validated['rate'] ?? null,
            );

            return $sale;
        });

        return redirect()->route('sales.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale')]));
    }

    public function destroy(Request $request, Sale $sale)
    {
        $sale->items()->delete();
        $sale->stockOuts()->delete();
        Transaction::where('reference_type', 'sale')->where('reference_id', $sale->id)->delete();
        $sale->transaction()->delete();
        $sale->delete();
        return redirect()->route('sales.index')->with('success', __('general.sale_deleted_successfully'));
    }

    public function restore(Request $request, Sale $sale)
    {
        $sale->restore();
        $sale->items()->restore();
        $sale->stockOuts()->restore();
        $sale->transaction()->restore();
        return redirect()->route('sales.index')->with('success', __('general.sale_restored_successfully'));
    }

    public function updateSaleStatus(Request $request, Sale $sale)
    {
        $sale->update(['status' => $request->status]);
        return back()->with('success', __('general.sale_status_updated_successfully'));
    }

    public function print(Request $request, Sale $sale)
    {

        $company = auth()->user()?->company;
        $sale = $sale->load([
            'customer',
            'items',
        ]);
        return inertia('Sale/Sales/Print', [
            'invoice' => new SaleResource($sale),
        'company' => $company,
        'sale_preference' => user_preference('sale.preference'),
        ]);

        // dd('hiiii');
        // $sale->load([
        //     'items.item',
        //     'items.unitMeasure',
        //     'customer',
        //     'transaction.currency',
        //     'stockOuts.store',
        // ]);

        // $company = auth()->user()?->company;

        // $html = view('sales.print', [
        //     'sale' => $sale,
        //     'company' => $company,
        // ])->render();

        // $tempDir = storage_path('app/mpdf-temp');
        // if (!is_dir($tempDir)) {
        //     mkdir($tempDir, 0775, true);
        // }

        // $mpdf = new Mpdf([
        //     'default_font_size' => 10,
        //     'default_font' => 'dejavusans',
        //     'tempDir' => $tempDir,
        //     'margin_top' => 15,
        //     'margin_bottom' => 15,
        //     'margin_left' => 10,
        //     'margin_right' => 10,
        // ]);

        // $mpdf->SetTitle('Sale #' . $sale->number);
        // $mpdf->WriteHTML($html);

        // return response($mpdf->Output('sale-'.$sale->number.'.pdf', 'S'), 200, [
        //     'Content-Type' => 'application/pdf',
        //     'Content-Disposition' => 'inline; filename="sale-'.$sale->number.'.pdf"',
        // ]);
    }
}

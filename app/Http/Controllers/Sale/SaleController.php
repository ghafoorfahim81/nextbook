<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SaleStoreRequest;
use App\Http\Requests\Sale\SaleUpdateRequest;
use App\Http\Resources\Sale\SaleResource;
use App\Models\Sale\Sale;
use App\Services\BillAllocationService;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Currency;
use App\Models\Administration\Warehouse;
use App\Models\User;
use Carbon\Carbon;
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
use App\Support\Preferences\InvoiceThemeOptions;
use Illuminate\Support\Facades\Cache;
use App\Models\Inventory\Item;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Services\DateConversionService;
class SaleController extends Controller
{
    private $dateConversionService;
    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Sale::class, 'sale');
        $this->dateConversionService = $dateConversionService;
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

        $sale = DB::transaction(function () use ($request, $transactionService, $stockService, $validated) {
            $validated = $request->validated();

            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : null;
            $validated['type'] = $validated['sale_type'] ?? 'cash';
            $validated['status'] = TransactionStatus::POSTED->value;

            $sale = Sale::create($validated);
            $totalDiscount = $request->input('discount_total', 0);
            $validated['item_list'] = array_map(function ($item) use ($validated) {
                $item['warehouse_id'] = $validated['warehouse_id'];
                return $item;
            }, $validated['item_list']);

            $sale->items()->createMany($validated['item_list']);

            $lines = [];
            $totalCostOfGoodsSold = 0;

            $glAccounts = Cache::get('gl_accounts');

            foreach ($validated['item_list'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $itemDiscount = isset($item['discount']) ? (float) $item['discount'] : 0;

                // Assumption:
                // item_discount is the TOTAL discount for this line, not per-unit discount.
                $lineGrossTotal = $quantity * $unitPrice;

                $itemModel = \App\Models\Inventory\Item::find($item['item_id']);
                $unitCost = (float) $itemModel->avgCost();
                $totalCost = $unitCost * $quantity;

                $totalCostOfGoodsSold += $totalCost;

                $stockService->post([
                    'item_id'         => $item['item_id'],
                    'movement_type'   => StockMovementType::OUT->value,
                    'unit_measure_id' => $item['unit_measure_id'],
                    'quantity'        => $quantity,
                    'source'          => StockSourceType::SALE->value,
                    'unit_cost'       => $unitCost,
                    'status'          => StockStatus::POSTED->value,
                    'batch'           => $item['batch'] ?? null,
                    'date'            => $date,
                    'expire_date'     => $item['expire_date'] ?? null,
                    'size_id'         => $validated['size_id'] ?? null,
                    'warehouse_id'    => $validated['warehouse_id'],
                    'branch_id'       => $sale->branch_id,
                    'reference_type'  => Sale::class,
                    'reference_id'    => $sale->id,
                ]);

                // Revenue at gross amount
                $lines[] = [
                    'account_id' => $itemModel->income_account_id,
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $lineGrossTotal,
                    'remark'     => 'Sale item: ' . $itemModel->name,
                ];

                // Cost of goods sold
                $lines[] = [
                    'account_id' => $itemModel->cost_account_id,
                    'ledger_id'  => null,
                    'debit'      => $totalCost,
                    'credit'     => 0,
                    'remark'     => 'COGS for sale item: ' . $itemModel->name,
                ];

                // Inventory reduction
                $lines[] = [
                    'account_id' => $itemModel->asset_account_id,
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $totalCost,
                    'remark'     => 'Inventory out for sale item: ' . $itemModel->name,
                ];
            }

            // Sales discount line
            if ($totalDiscount > 0) {
                $lines[] = [
                    'account_id' => $glAccounts['discount-to-customer'], // must exist in your cache/accounts setup
                    'ledger_id'  => null,
                    'debit'      => $totalDiscount,
                    'credit'     => 0,
                    'remark'     => 'Sales discount for sale #' . $sale->number,
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {
                $lines[] = [
                    'account_id' => $validated['bank_account_id'],
                    'ledger_id'  => null,
                    'debit'      => $validated['transaction_total'],
                    'credit'     => 0,
                    'remark'     => 'Cash received for sale #' . $sale->number,
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
                $lines[] = [
                    'account_id' => $glAccounts['account-receivable'],
                    'ledger_id'  => $validated['customer_id'],
                    'debit'      => $validated['transaction_total'],
                    'credit'     => 0,
                    'remark'     => 'Receivable for sale #' . $sale->number,
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Credit->value) {
                $paidAmount = (float) ($validated['payment']['amount'] ?? 0);

                if ($paidAmount > 0) {
                    $lines[] = [
                        'account_id' => $validated['payment']['account_id'],
                        'ledger_id'  => null,
                        'debit'      => $paidAmount,
                        'credit'     => 0,
                        'remark'     => 'Partial payment for sale #' . $sale->number,
                    ];

                    $remaining = $validated['transaction_total'] - $paidAmount;

                    if ($remaining > 0) {
                        $lines[] = [
                            'account_id' => $glAccounts['account-receivable'],
                            'ledger_id'  => $validated['customer_id'],
                            'debit'      => $remaining,
                            'credit'     => 0,
                            'remark'     => 'Remaining receivable for sale #' . $sale->number,
                        ];
                    }
                } else {
                    $lines[] = [
                        'account_id' => $glAccounts['account-receivable'],
                        'ledger_id'  => $validated['customer_id'],
                        'debit'      => $validated['transaction_total'],
                        'credit'     => 0,
                        'remark'     => 'Receivable for sale #' . $sale->number,
                    ];
                }
            }

            $transactionService->post(
                header: [
                    'currency_id'    => $validated['currency_id'],
                    'rate'           => $validated['rate'],
                    'date'           => $date,
                    'remark'         => 'Sale #' . $sale->number,
                    'status'         => TransactionStatus::POSTED->value,
                    'reference_type' => Sale::class,
                    'reference_id'   => $sale->id,
                ],
                lines: $lines
            );

            app(BillAllocationService::class)->recalculateSalePaymentStatuses([$sale->id]);

            return $sale;
        });

        if ((bool) $request->create_and_new) {
            return redirect()->back()->with(
                'success',
                __('general.created_successfully', ['resource' => __('general.resource.sale')])
            );
        }

        $redirect = redirect()->route('sales.index')->with(
            'success',
            __('general.created_successfully', ['resource' => __('general.resource.sale')])
        );

        if ((bool) $request->create_and_print) {
            $redirect->with('print_url', route('sales.print', $sale));
        }

        return $redirect;
    }
    // public function store(SaleStoreRequest $request, TransactionService $transactionService, StockService $stockService)
    // {
    //     $validated = $request->validated();
    //     $sale = DB::transaction(function () use ($request, $transactionService, $stockService, $validated) {
    //         // Create purchase
    //         $validated = $request->validated();

    //         $validated['type']  = $validated['sale_type'] ?? 'cash';
    //         $validated['status'] = TransactionStatus::POSTED->value;

    //         $sale = Sale::create($validated);
    //         $validated['item_list'] = array_map(function ($item) use ($validated) {
    //             $item['discount'] = $item['item_discount'] ? $item['item_discount'] : 0;
    //             $item['warehouse_id'] = $validated['warehouse_id'];
    //             return $item;
    //         }, $validated['item_list']);
    //         $sale->items()->createMany($validated['item_list']);

    //         $lines = [];
    //         foreach ($validated['item_list'] as $item) {
    //             $total = (float) $item['quantity'] * (float) $item['unit_price'];
    //             $itemModel = \App\Models\Inventory\Item::find($item['item_id']);
    //             $unitCost = $itemModel->avgCost();
    //             $totalCost = $unitCost * $item['quantity'];
    //             $stock = $stockService->post([
    //                 'item_id'         => $item['item_id'],
    //                 'movement_type'   => StockMovementType::OUT->value,
    //                 'unit_measure_id' => $item['unit_measure_id'], // from item form
    //                 'quantity'        => (float) $item['quantity'],
    //                 'source'          => StockSourceType::SALE->value,
    //                 'unit_cost'       => $unitCost,
    //                 'status'          => StockStatus::POSTED->value,
    //                 'batch'           => $item['batch'] ?? null,
    //                 'date'            => $validated['date'],
    //                 'expire_date'     => $item['expire_date'],
    //                 'size_id'         => $validated['size_id'] ?? null,
    //                 'warehouse_id'    => $validated['warehouse_id'],
    //                 'branch_id'       => $sale->branch_id,
    //                 'reference_type'  => Sale::class,
    //                 'reference_id'    => $sale->id,
    //             ]);
    //             $lines[] = [
    //                 'account_id' => $itemModel->income_account_id,
    //                 'ledger_id'  => null,
    //                 'debit'      => 0,
    //                 'credit'     => $total,
    //                 'remark'     => 'Sale item: '.$itemModel->name,
    //             ];
    //             $lines[] = [
    //                 'account_id' => $itemModel->cost_account_id,
    //                 'ledger_id'  => null,
    //                 'debit'      => $totalCost,
    //                 'credit'     => 0,
    //                 'remark'     => 'Sale item: '.$itemModel->name,
    //             ];
    //             $lines[] = [
    //                 'account_id' => $itemModel->asset_account_id,
    //                 'ledger_id'  => null,
    //                 'debit'      => 0,
    //                 'credit'     => $totalCost,
    //                 'remark'     => 'Sale item: '.$itemModel->name,
    //             ];

    //             // $stockService->addStock($item, $validated['warehouse_id'], Sale::class, $sale->id, $validated['date']);
    //         }
    //         if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {

    //             $lines[] = [
    //                 'account_id' => $validated['bank_account_id'], // cash/bank
    //                 'ledger_id'  => null,
    //                 'debit'      => $validated['transaction_total'],
    //                 'credit'     => 0,
    //                 'remark'     => 'Customer payment received for sale #' . $sale->number,
    //             ];
    //         }
    //         $glAccounts = Cache::get('gl_accounts');
    //         if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
    //             $lines[] = [
    //                 'account_id' => $glAccounts['account-receivable'],
    //                 'ledger_id'  => $validated['customer_id'],
    //                 'debit'      => $validated['transaction_total'],
    //                 'credit'     => 0,
    //                 'remark'     => 'Customer payment received for sale #' . $sale->number,
    //             ];
    //         }
    //         if($validated['type'] === \App\Enums\SalePurchaseType::Credit->value) {
    //             if($validated['payment']['amount'] > 0) {
    //                 $amount = (float) $validated['payment']['amount'];
    //                 $lines[] = [
    //                     'account_id' => $validated['payment']['account_id'],
    //                     'debit' => $amount,
    //                     'credit' => 0,
    //                 ];
    //                 $lines[] = [
    //                     'account_id' => $glAccounts['account-receivable'],
    //                     'ledger_id' => $validated['customer_id'],
    //                     'debit' => $validated['transaction_total'] - $amount,
    //                     'credit' => 0,
    //                     'remark' => 'Customer payment received for sale #' . $sale->number,
    //                 ];
    //             }
    //             else{
    //                 $lines[] = [
    //                     'account_id' => $glAccounts['account-receivable'],
    //                     'ledger_id' => $validated['customer_id'],
    //                     'debit' => $validated['transaction_total'],
    //                     'credit' => 0,
    //                     'remark' => 'Payment of sale #' . $sale->number,
    //                 ];
    //             }
    //         }

    //         $transactionService->post(
    //             header: [
    //                 'currency_id'   => $validated['currency_id'],
    //                 'rate'          => $validated['rate'],
    //                 'date'          => $validated['date'],
    //                 'remark'        => 'Sale #' . $sale->number,
    //                 'status'        => TransactionStatus::POSTED->value,
    //                 'reference_type'=> Sale::class,
    //                 'reference_id'  => $sale->id,
    //             ],
    //             lines: $lines
    //         );


    //         // Create accounting transactions


    //         return $sale;
    //     });

    //     if ((bool) $request->create_and_new) {
    //         // Stay on the same page; frontend will reset form and increment number
    //         return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.sale')]));
    //     }

    //     return redirect()->route('sales.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.sale')]));
    // }

    public function show(Request $request, Sale $sale)
    {
        $sale->load(['items.item', 'items.unitMeasure', 'customer', 'transaction.currency', 'createdBy']);

        return response()->json([
            'data' => new SaleResource($sale),
        ]);
    }

    public function edit(Request $request, Sale $sale)
    {
        $bankAccounts = (new Account())->getAccountsByAccountTypeSlug('cash-or-bank');

        return inertia('Sale/Sales/Edit', [
            'sale' => new SaleResource($sale->load([
                'items.item.unitMeasure',
                'items.unitMeasure',
                'items.warehouse',
                'customer',
                'transaction.currency',
                'transaction.lines.account',
                'transaction.lines.ledger',
            ])),
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function update(SaleUpdateRequest $request, Sale $sale, TransactionService $transactionService, StockService $stockService)
    {
        $sale = DB::transaction(function () use ($request, $sale, $transactionService, $stockService) {
            $validated = $request->validated();
            $validated['type'] = $validated['sale_type'] ?? $sale->type ?? 'cash';
            $validated['status'] = TransactionStatus::POSTED->value;

            $date = $validated['date'] ? $this->dateConversionService->toGregorian($validated['date']) : $sale->date;
            $affectedCombos = $sale->items()
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($item) => [
                    'item_id' => $item->item_id,
                    'warehouse_id' => $item->warehouse_id,
                    'branch_id' => $item->branch_id ?? $sale->branch_id,
                ])
                ->all();

            $validated['item_list'] = array_map(function ($item) use ($validated, $sale, &$affectedCombos) {
                $item['discount'] = $item['item_discount'] ?? 0;
                $item['warehouse_id'] = $validated['warehouse_id'];

                $affectedCombos[] = [
                    'item_id' => $item['item_id'],
                    'warehouse_id' => $validated['warehouse_id'],
                    'branch_id' => $sale->branch_id,
                ];

                return $item;
            }, $validated['item_list']);

            $sale->update($validated);

            $sale->items()->forceDelete();

            StockMovement::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->forceDelete();

            $this->rebuildStockStateForCombos($affectedCombos);

            $sale->items()->createMany($validated['item_list']);

            $transaction = Transaction::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();

            if ($transaction) {
                $transaction->lines()->forceDelete();
                $transaction->forceDelete();
            }

            $lines = [];
            $totalDiscount = (float) $request->input('discount_total', 0);
            $glAccounts = Cache::get('gl_accounts');

            foreach ($validated['item_list'] as $item) {
                $quantity = (float) $item['quantity'];
                $unitPrice = (float) $item['unit_price'];
                $lineGrossTotal = $quantity * $unitPrice;

                $itemModel = Item::findOrFail($item['item_id']);
                $unitCost = (float) ($itemModel->avgCost() ?? 0);
                $totalCost = $unitCost * $quantity;

                $stockService->post([
                    'item_id'         => $item['item_id'],
                    'movement_type'   => StockMovementType::OUT->value,
                    'unit_measure_id' => $item['unit_measure_id'],
                    'quantity'        => $quantity,
                    'source'          => StockSourceType::SALE->value,
                    'unit_cost'       => $unitCost,
                    'status'          => StockStatus::POSTED->value,
                    'batch'           => $item['batch'] ?? null,
                    'date'            => $date,
                    'expire_date'     => $item['expire_date'] ?? null,
                    'size_id'         => $validated['size_id'] ?? null,
                    'warehouse_id'    => $validated['warehouse_id'],
                    'branch_id'       => $sale->branch_id,
                    'reference_type'  => Sale::class,
                    'reference_id'    => $sale->id,
                ]);

                $lines[] = [
                    'account_id' => $itemModel->income_account_id,
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $lineGrossTotal,
                    'remark'     => 'Sale item: ' . $itemModel->name,
                ];

                $lines[] = [
                    'account_id' => $itemModel->cost_account_id,
                    'ledger_id'  => null,
                    'debit'      => $totalCost,
                    'credit'     => 0,
                    'remark'     => 'COGS for sale item: ' . $itemModel->name,
                ];

                $lines[] = [
                    'account_id' => $itemModel->asset_account_id,
                    'ledger_id'  => null,
                    'debit'      => 0,
                    'credit'     => $totalCost,
                    'remark'     => 'Inventory out for sale item: ' . $itemModel->name,
                ];
            }

            if ($totalDiscount > 0) {
                $lines[] = [
                    'account_id' => $glAccounts['discount-to-customer'],
                    'ledger_id'  => null,
                    'debit'      => $totalDiscount,
                    'credit'     => 0,
                    'remark'     => 'Sales discount for sale #' . $sale->number,
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Cash->value) {
                $lines[] = [
                    'account_id' => $validated['bank_account_id'],
                    'ledger_id'  => null,
                    'debit'      => $validated['transaction_total'],
                    'credit'     => 0,
                    'remark'     => 'Cash received for sale #' . $sale->number,
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::OnLoan->value) {
                $lines[] = [
                    'account_id' => $glAccounts['account-receivable'],
                    'ledger_id'  => $validated['customer_id'],
                    'debit'      => $validated['transaction_total'],
                    'credit'     => 0,
                    'remark'     => 'Receivable for sale #' . $sale->number,
                ];
            }

            if ($validated['type'] === \App\Enums\SalePurchaseType::Credit->value) {
                $paidAmount = (float) ($validated['payment']['amount'] ?? 0);

                if ($paidAmount > 0) {
                    $lines[] = [
                        'account_id' => $validated['payment']['account_id'],
                        'ledger_id'  => null,
                        'debit'      => $paidAmount,
                        'credit'     => 0,
                        'remark'     => 'Partial payment for sale #' . $sale->number,
                    ];

                    $remaining = $validated['transaction_total'] - $paidAmount;

                    if ($remaining > 0) {
                        $lines[] = [
                            'account_id' => $glAccounts['account-receivable'],
                            'ledger_id'  => $validated['customer_id'],
                            'debit'      => $remaining,
                            'credit'     => 0,
                            'remark'     => 'Remaining receivable for sale #' . $sale->number,
                        ];
                    }
                } else {
                    $lines[] = [
                        'account_id' => $glAccounts['account-receivable'],
                        'ledger_id'  => $validated['customer_id'],
                        'debit'      => $validated['transaction_total'],
                        'credit'     => 0,
                        'remark'     => 'Receivable for sale #' . $sale->number,
                    ];
                }
            }

            $transactionService->post(
                header: [
                    'currency_id'    => $validated['currency_id'],
                    'rate'           => $validated['rate'],
                    'date'           => $date,
                    'remark'         => 'Sale #' . $sale->number,
                    'status'         => TransactionStatus::POSTED->value,
                    'reference_type' => Sale::class,
                    'reference_id'   => $sale->id,
                ],
                lines: $lines
            );

            app(BillAllocationService::class)->recalculateSalePaymentStatuses([$sale->id]);

            return $sale;
        });

        $redirect = redirect()->route('sales.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.sale')]));

        if ($request->boolean('save_and_print')) {
            $redirect->with('print_url', route('sales.print', $sale));
        }

        return $redirect;
    }

    private function rebuildStockStateForCombos(array $combos): void
    {
        $uniqueCombos = collect($combos)
            ->filter(fn ($combo) => !empty($combo['item_id']) && !empty($combo['warehouse_id']) && !empty($combo['branch_id']))
            ->unique(fn ($combo) => implode('|', [
                $combo['branch_id'],
                $combo['warehouse_id'],
                $combo['item_id'],
            ]))
            ->values();

        foreach ($uniqueCombos as $combo) {
            $this->rebuildStockStateForItemWarehouse(
                branchId: $combo['branch_id'],
                warehouseId: $combo['warehouse_id'],
                itemId: $combo['item_id'],
            );
        }
    }

    private function rebuildStockStateForItemWarehouse(string $branchId, string $warehouseId, string $itemId): void
    {
        $item = Item::find($itemId);

        if (!$item) {
            return;
        }

        $movements = StockMovement::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        StockBalance::query()
            ->where('branch_id', $branchId)
            ->where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->forceDelete();

        if ($movements->isEmpty()) {
            return;
        }

        $balanceBuckets = [];

        foreach ($movements as $movement) {
            $status = $movement->status?->value ?? $movement->status;
            $expireDate = $movement->expire_date?->toDateString();
            $bucketKey = implode('|', [
                $status,
                $movement->batch ?? '',
                $expireDate ?? '',
            ]);

            if (!isset($balanceBuckets[$bucketKey])) {
                $balanceBuckets[$bucketKey] = [
                    'branch_id' => $branchId,
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'batch' => $movement->batch,
                    'expire_date' => $expireDate,
                    'status' => $status,
                    'quantity' => 0,
                    'in_quantity' => 0,
                    'in_value' => 0,
                ];
            }

            if ($movement->movement_type === StockMovementType::IN) {
                $balanceBuckets[$bucketKey]['quantity'] += (float) $movement->quantity;
                $balanceBuckets[$bucketKey]['in_quantity'] += (float) $movement->quantity;
                $balanceBuckets[$bucketKey]['in_value'] += (float) $movement->quantity * (float) $movement->unit_cost;
            } else {
                $balanceBuckets[$bucketKey]['quantity'] -= (float) $movement->quantity;
            }
        }

        foreach ($balanceBuckets as $bucket) {
            if ($bucket['quantity'] <= 0) {
                continue;
            }

            StockBalance::create([
                'branch_id' => $bucket['branch_id'],
                'item_id' => $bucket['item_id'],
                'warehouse_id' => $bucket['warehouse_id'],
                'batch' => $bucket['batch'],
                'expire_date' => $bucket['expire_date'],
                'status' => $bucket['status'],
                'quantity' => $bucket['quantity'],
                'average_cost' => $bucket['in_quantity'] > 0
                    ? $bucket['in_value'] / $bucket['in_quantity']
                    : 0,
            ]);
        }

        $inMovements = $movements
            ->filter(fn ($movement) => $movement->movement_type === StockMovementType::IN)
            ->values();

        foreach ($inMovements as $movement) {
            $movement->qty_remaining = (float) $movement->quantity;
            $movement->save();
        }

        $outMovements = $movements
            ->filter(fn ($movement) => $movement->movement_type === StockMovementType::OUT)
            ->values();

        foreach ($outMovements as $outMovement) {
            $remaining = (float) $outMovement->quantity;

            foreach ($inMovements as $inMovement) {
                if ($remaining <= 0) {
                    break;
                }

                if ($item->is_batch_tracked && ($inMovement->batch ?? null) !== ($outMovement->batch ?? null)) {
                    continue;
                }

                if ($outMovement->expire_date && $inMovement->expire_date?->toDateString() !== $outMovement->expire_date?->toDateString()) {
                    continue;
                }

                $available = (float) ($inMovement->qty_remaining ?? 0);

                if ($available <= 0) {
                    continue;
                }

                $deduct = min($available, $remaining);
                $inMovement->qty_remaining = $available - $deduct;
                $inMovement->save();
                $remaining -= $deduct;
            }
        }
    }

    public function destroy(Request $request, Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            $affectedCombos = $sale->items()
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($item) => [
                    'item_id' => $item->item_id,
                    'warehouse_id' => $item->warehouse_id,
                    'branch_id' => $item->branch_id ?? $sale->branch_id,
                ])
                ->all();

            $stockMovementCombos = StockMovement::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($movement) => [
                    'item_id' => $movement->item_id,
                    'warehouse_id' => $movement->warehouse_id,
                    'branch_id' => $movement->branch_id,
                ])
                ->all();

            $transaction = Transaction::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();

            if ($transaction) {
                $transaction->lines()->delete();
                $transaction->delete();
            }

            StockMovement::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->delete();

            $sale->items()->delete();
            $sale->delete();

            $this->rebuildStockStateForCombos([
                ...$affectedCombos,
                ...$stockMovementCombos,
            ]);
        });

        return redirect()->route('sales.index')->with('success', __('general.sale_deleted_successfully'));
    }

    public function restore(Request $request, Sale $sale)
    {
        DB::transaction(function () use ($sale) {
            $sale->restore();
            $sale->items()->withTrashed()->restore();

            StockMovement::withTrashed()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->restore();

            $transaction = Transaction::withTrashed()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->first();

            if ($transaction) {
                $transaction->restore();
                $transaction->lines()->withTrashed()->restore();
            }

            $affectedCombos = $sale->items()
                ->withTrashed()
                ->get(['item_id', 'warehouse_id', 'branch_id'])
                ->map(fn ($item) => [
                    'item_id' => $item->item_id,
                    'warehouse_id' => $item->warehouse_id,
                    'branch_id' => $item->branch_id ?? $sale->branch_id,
                ])
                ->all();

            $this->rebuildStockStateForCombos($affectedCombos);
        });

        return redirect()->route('sales.index')->with('success', __('general.sale_restored_successfully'));
    }

    public function updateSaleStatus(Request $request, Sale $sale)
    {
        $sale->update(['status' => $request->status]);
        return back()->with('success', __('general.sale_status_updated_successfully'));
    }

    public function openBills(Request $request, BillAllocationService $billAllocationService)
    {
        $ledgerId = (string) $request->query('ledger_id', '');
        $excludeReceiptId = (string) $request->query('exclude_receipt_id', '');

        return response()->json([
            'data' => $ledgerId ? $billAllocationService->openSalesForCustomer($ledgerId, $excludeReceiptId ?: null) : [],
        ]);
    }

    public function print(Request $request, Sale $sale)
    {

        $company = auth()->user()?->company;
        $sale = $sale->load([
            'customer',
            'items.item',
            'items.unitMeasure',
            'items.warehouse',
            'transaction.currency',
            'transaction.lines',
        ]);
        return inertia('Sale/Sales/Print', [
            'invoice' => new SaleResource($sale),
            'company' => $company,
            'invoiceTheme' => user_preference('sale.invoice_theme', InvoiceThemeOptions::DEFAULT),
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

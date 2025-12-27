<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SaleStoreRequest;
use App\Http\Requests\Sale\SaleUpdateRequest;
use App\Http\Resources\Sale\SaleResource;
use App\Models\Sale\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TransactionService;
use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Services\StockService;
use App\Models\Transaction\Transaction;
use Mpdf\Mpdf;
class SaleController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Sale::class, 'sale');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $sales = Sale::with('customer')
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Sale/Sales/Index', [
            'sales' => SaleResource::collection($sales),
        ]);
    }

    public function create(Request $request)
    {
        $saleNumber = Sale::max('number') ? Sale::max('number') + 1 : 1;

        return inertia('Sale/Sales/Create', [
            'saleNumber' => $saleNumber,
        ]);
    }

    public function store(SaleStoreRequest $request, TransactionService $transactionService, StockService $stockService)
    {
        $sale = DB::transaction(function () use ($request, $transactionService, $stockService) {
            // Create sale
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();

            $validated['type']  = $validated['sale_purchase_type_id'] ?? null;
            $validated['status'] = 'pending';

            // Convert date properly
            $validated['date'] = $dateConversionService->toGregorian($validated['date']);

            $sale = Sale::create($validated);
            $validated['item_list'] = array_map(function ($item) use ($dateConversionService) {
                $item['expire_date'] = $item['expire_date'] ? $dateConversionService->toGregorian($item['expire_date']) : null;
                $item['discount'] = $item['item_discount'] ? $item['item_discount'] : 0;
                return $item;
            }, $validated['item_list']);
            $sale->items()->createMany($validated['item_list']);

            // Handle stock deductions (reverse of purchase - remove from inventory)
            foreach ($validated['item_list'] as $item) {
                $stockService->removeStock($item, $validated['store_id'], Sale::class, $sale->id);
            }

            // Create accounting transactions (reverse of purchase)
            $transactions = $transactionService->createSaleTransactions(
                $sale,
                \App\Models\Ledger\Ledger::find($validated['customer_id']),
                $validated['transaction_total'],
                $validated['sale_purchase_type_id'],
                $validated['payment'] ?? [],
                $validated['currency_id'] ?? null,
                $validated['rate'] ?? null,
            );

            return $sale;
        });

        if ((bool) $request->create_and_new) {
            // Stay on the same page; frontend will reset form and increment number
            return redirect()->back()->with('success', 'Sale created successfully.');
        }

        return redirect()->route('sales.index')->with('success', 'Sale created successfully.');
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
                $validated['item_list'] = array_map(function ($item) use ($dateConversionService) {
                    $item['expire_date'] = $item['expire_date'] ? $dateConversionService->toGregorian($item['expire_date']) : null;
                    $item['discount'] = $item['discount'] ? $item['discount'] : 0;
                    return $item;
                }, $validated['item_list']);
                $sale->items()->createMany($validated['item_list']);
                $sale->stockOuts()->forceDelete();
                // Add new stock out entries
                foreach ($validated['item_list'] as $item) {
                    $stockService->removeStock($item, $validated['store_id'], 'sale', $sale->id);
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

        return redirect()->route('sales.index')->with('success', 'Sale updated successfully.');
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
        $sale->load([
            'items.item',
            'items.unitMeasure',
            'customer',
            'transaction.currency',
            'stockOuts.store',
        ]);

        $company = auth()->user()?->company;

        $html = view('sales.print', [
            'sale' => $sale,
            'company' => $company,
        ])->render();

        $tempDir = storage_path('app/mpdf-temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new Mpdf([
            'default_font_size' => 10,
            'default_font' => 'dejavusans',
            'tempDir' => $tempDir,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_left' => 10,
            'margin_right' => 10,
        ]);

        $mpdf->SetTitle('Sale #' . $sale->number);
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('sale-'.$sale->number.'.pdf', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="sale-'.$sale->number.'.pdf"',
        ]);
    }
}
